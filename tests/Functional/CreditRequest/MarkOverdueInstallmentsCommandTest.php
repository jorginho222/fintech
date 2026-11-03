<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MarkOverdueInstallmentsCommandTest extends KernelTestCase
{
    public function testMarksInstallmentsPastTheirDueDateAsOverdue(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );
        $em->persist($company);

        $creditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Crédito 1',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Active,
        );
        $em->persist($creditRequest);

        $overdueInstallment = new Installment(
            'cccccccc-cccc-4ccc-cccc-cccccccccccc',
            1,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.33',
            InstallmentStatus::Pending,
            $creditRequest,
        );
        $overdueInstallment->changeDueDate(new \DateTimeImmutable('-1 day'));
        $creditRequest->addInstallment($overdueInstallment);
        $em->persist($overdueInstallment);
        $em->flush();
        $em->clear();

        $application = new Application(self::$kernel);
        $command = $application->find('app:credit-request:mark-overdue-installments');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $refreshedInstallment = $em->find(Installment::class, $overdueInstallment->getId());
        self::assertSame(InstallmentStatus::Overdue, $refreshedInstallment->getStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(), 'Cuota vencida');
    }

    public function testSkipsProcessingPaymentsAndMarksOtherAttemptsOverdue(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456780', 'empresa@example.com', TaxStatus::Monotributo);
        $em->persist($company);
        $creditRequest = new CreditRequest('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Crédito 1', '10000000', '60.00', 12, $company, CreditRequestStatus::Active);
        $em->persist($creditRequest);

        $now = new \DateTimeImmutable();
        $processing = $this->pastDueInstallment($creditRequest, 1);
        $processing->startPaymentAttempt($now);
        $rejected = $this->pastDueInstallment($creditRequest, 2);
        $rejected->startPaymentAttempt($now);
        $rejected->rejectPaymentAttempt($now);
        $failed = $this->pastDueInstallment($creditRequest, 3);
        $failed->startPaymentAttempt($now);
        $failed->failPaymentAttempt($now, 'Gateway error');
        $timedOut = $this->pastDueInstallment($creditRequest, 4);
        $timedOut->startPaymentAttempt($now);
        $timedOut->timeoutPaymentAttempt($now);
        foreach ([$processing, $rejected, $failed, $timedOut] as $installment) {
            $em->persist($installment);
        }
        $em->flush();
        $em->clear();

        $commandTester = new CommandTester((new Application(self::$kernel))->find('app:credit-request:mark-overdue-installments'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertSame(InstallmentStatus::Pending, $em->find(Installment::class, $processing->getId())->getStatus());
        self::assertSame('0.0000', $em->find(Installment::class, $processing->getId())->getPenaltyInterestAmount());

        $overdueRejected = $em->find(Installment::class, $rejected->getId());
        self::assertSame(InstallmentStatus::Overdue, $overdueRejected->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Rejected, $overdueRejected->getLastPaymentAttemptStatus());
        self::assertSame(InstallmentStatus::Overdue, $em->find(Installment::class, $failed->getId())->getStatus());
        self::assertSame(InstallmentStatus::Overdue, $em->find(Installment::class, $timedOut->getId())->getStatus());
        self::assertQueuedEmailCount(3);
    }

    public function testChargesEveryDayPastTheDueDate(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456780', 'empresa@example.com', TaxStatus::Monotributo);
        $em->persist($company);
        $creditRequest = new CreditRequest('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Crédito 1', '10000000', '60.00', 12, $company, CreditRequestStatus::Active);
        $em->persist($creditRequest);
        $installment = $this->pastDueInstallment($creditRequest, 1);
        $installment->changeDueDate((new \DateTimeImmutable('today'))->modify('-3 days'));
        $em->persist($installment);
        $em->flush();
        $em->clear();

        $commandTester = new CommandTester((new Application(self::$kernel))->find('app:credit-request:mark-overdue-installments'));
        $commandTester->execute([]);

        // Three days of the 2054.79 charged per day.
        self::assertSame(6164.37, (float) $em->find(Installment::class, $installment->getId())->getPenaltyInterestAmount());
    }

    private function pastDueInstallment(CreditRequest $creditRequest, int $periodNumber): Installment
    {
        $installment = new Installment(
            sprintf('cccccccc-cccc-4ccc-8ccc-%012d', $periodNumber),
            $periodNumber,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.33',
            InstallmentStatus::Pending,
            $creditRequest,
        );
        $installment->changeDueDate(new \DateTimeImmutable('-1 day'));

        return $installment;
    }
}
