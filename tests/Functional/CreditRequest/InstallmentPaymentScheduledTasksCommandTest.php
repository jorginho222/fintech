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

final class InstallmentPaymentScheduledTasksCommandTest extends KernelTestCase
{
    private const string ACCRUE_COMMAND = 'app:credit-request:accrue-penalty-interest';

    // 90% yearly over 365 days, on the 1000.00 of capital of the installments created below.
    private const float PENALTY_PER_DAY = 2.47;

    public function testTimesOutOnlyAttemptsPastTheLimit(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $expired = $this->installment($creditRequest, 1, '+1 day');
        $expired->startPaymentAttempt(new \DateTimeImmutable('-10 days'));
        $recent = $this->installment($creditRequest, 2, '+1 day');
        $recent->startPaymentAttempt(new \DateTimeImmutable());
        $this->em()->flush();

        $tester = $this->runCommand('app:credit-request:timeout-payment-attempts');

        self::assertStringContainsString('Marked 1 payment attempt(s) as timed out.', $tester->getDisplay());
        $this->em()->clear();
        self::assertSame(InstallmentPaymentAttemptStatus::ProcessingTimeout, $this->em()->find(Installment::class, $expired->getId())->getLastPaymentAttemptStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $this->em()->find(Installment::class, $recent->getId())->getLastPaymentAttemptStatus());
        self::assertQueuedEmailCount(1);
        self::assertEmailSubjectContains(self::getMailerMessage(), 'No se pudo confirmar el pago');
    }

    public function testRemindsOnlyPendingInstallmentsDueInThreeDaysWithoutAProcessingPayment(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $this->installment($creditRequest, 1, '+3 days');
        $this->installment($creditRequest, 2, '+2 days');
        $this->installment($creditRequest, 3, '+4 days');
        $this->installment($creditRequest, 4, '+3 days')->startPaymentAttempt(new \DateTimeImmutable());
        $rejected = $this->installment($creditRequest, 5, '+3 days');
        $rejected->startPaymentAttempt(new \DateTimeImmutable());
        $rejected->rejectPaymentAttempt(new \DateTimeImmutable());
        $this->em()->flush();

        $tester = $this->runCommand('app:credit-request:send-due-date-reminders');

        self::assertStringContainsString('Sent 2 due date reminder(s).', $tester->getDisplay());
        self::assertQueuedEmailCount(2);
        self::assertEmailSubjectContains(self::getMailerMessage(), 'Recordatorio de vencimiento');
    }

    public function testChargesOverdueInstallmentsForAllTheirDaysWithoutEmailAndOnlyOncePerDay(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $installment = $this->overdueInstallment($creditRequest, 1, '-3 days');
        $this->em()->flush();

        $tester = $this->runCommand(self::ACCRUE_COMMAND);

        self::assertStringContainsString('Charged late-payment interest on 1 installment(s).', $tester->getDisplay());
        $this->em()->clear();
        $charged = $this->em()->find(Installment::class, $installment->getId());
        self::assertSame(self::PENALTY_PER_DAY * 3, (float) $charged->getPenaltyInterestAmount());
        self::assertGreaterThan(1000.0, (float) $charged->getTotalAmount());
        self::assertQueuedEmailCount(0);

        $tester = $this->runCommand(self::ACCRUE_COMMAND);

        self::assertStringContainsString('Charged late-payment interest on 0 installment(s).', $tester->getDisplay());
    }

    public function testCatchesUpFromTheLastCalculation(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $installment = $this->overdueInstallment($creditRequest, 1, '-10 days');
        $installment->setLastPenaltyCalculationAt((new \DateTimeImmutable('today'))->modify('-2 days'));
        $this->em()->flush();

        $this->runCommand(self::ACCRUE_COMMAND);

        $this->em()->clear();
        self::assertSame(self::PENALTY_PER_DAY * 2, (float) $this->em()->find(Installment::class, $installment->getId())->getPenaltyInterestAmount());
    }

    public function testPausesWhileProcessingAndChargesThePausedDaysAfterARejection(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $installment = $this->overdueInstallment($creditRequest, 1, '-3 days');
        $installment->startPaymentAttempt(new \DateTimeImmutable('-3 days'));
        $this->em()->flush();

        $tester = $this->runCommand(self::ACCRUE_COMMAND);

        self::assertStringContainsString('on 0 installment(s).', $tester->getDisplay());
        self::assertSame('1000.00', $installment->getTotalAmount());

        $installment->rejectPaymentAttempt(new \DateTimeImmutable());
        $this->em()->flush();

        $this->runCommand(self::ACCRUE_COMMAND);

        $this->em()->clear();
        self::assertSame(self::PENALTY_PER_DAY * 3, (float) $this->em()->find(Installment::class, $installment->getId())->getPenaltyInterestAmount());
    }

    public function testAnApprovedPaymentIsNotChargedForThePausedDays(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $installment = $this->overdueInstallment($creditRequest, 1, '-3 days');
        $installment->startPaymentAttempt(new \DateTimeImmutable('-3 days'));
        $installment->approvePaymentAttempt(new \DateTimeImmutable());
        $this->em()->flush();

        $tester = $this->runCommand(self::ACCRUE_COMMAND);

        self::assertStringContainsString('on 0 installment(s).', $tester->getDisplay());
        self::assertSame('1000.00', $installment->getTotalAmount());
    }

    public function testALateApprovalPaysTheInstallmentForgivingTheInterestChargedAfterTheTimeout(): void
    {
        self::bootKernel();
        $creditRequest = $this->creditRequest();
        $installment = $this->overdueInstallment($creditRequest, 1, '-3 days');
        $installment->startPaymentAttempt(new \DateTimeImmutable('-3 days'));
        $installment->timeoutPaymentAttempt(new \DateTimeImmutable());
        $this->em()->flush();

        $this->runCommand(self::ACCRUE_COMMAND);
        $totalWithInterest = $installment->getTotalAmount();
        self::assertGreaterThan(1000.0, (float) $totalWithInterest);

        self::assertTrue($installment->approvePaymentAttempt(new \DateTimeImmutable()));
        $this->em()->flush();

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame($totalWithInterest, $installment->getTotalAmount());
    }

    private function overdueInstallment(CreditRequest $creditRequest, int $periodNumber, string $dueIn): Installment
    {
        $installment = $this->installment($creditRequest, $periodNumber, $dueIn);
        $installment->changeStatus(InstallmentStatus::Overdue);

        return $installment;
    }

    private function runCommand(string $name): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($name));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function creditRequest(): CreditRequest
    {
        $company = new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456780', 'empresa@example.com', TaxStatus::Monotributo);
        $this->em()->persist($company);

        $creditRequest = new CreditRequest('10000000-0000-4000-8000-000000000000', 'Crédito 1', '100000', '55', 12, $company, CreditRequestStatus::Active);
        $this->em()->persist($creditRequest);

        return $creditRequest;
    }

    private function installment(CreditRequest $creditRequest, int $periodNumber, string $dueIn): Installment
    {
        $installment = new Installment(
            substr($creditRequest->getId(), 0, -12) . sprintf('%012d', $periodNumber),
            $periodNumber,
            '1000.00',
            '0.00',
            '0.00',
            '1000.00',
            InstallmentStatus::Pending,
            $creditRequest,
        );
        $installment->changeDueDate((new \DateTimeImmutable('today'))->modify($dueIn));
        $this->em()->persist($installment);

        return $installment;
    }
}
