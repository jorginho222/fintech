<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
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
            'hashed-password',
        );
        $em->persist($company);

        $creditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
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
    }
}
