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

final class DeleteExpiredCreditRequestProposalsCommandTest extends KernelTestCase
{
    public function testDeletesExpiredProposalsPastTheirRetentionPeriod(): void
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

        $expiredCreditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::ProposalExpired,
        );
        $em->persist($expiredCreditRequest);

        $installment = new Installment(
            'cccccccc-cccc-4ccc-cccc-cccccccccccc',
            1,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.33',
            InstallmentStatus::Pending,
            $expiredCreditRequest,
        );
        $expiredCreditRequest->addInstallment($installment);
        $em->persist($installment);
        $em->flush();

        // backdate the approval limit date directly, since it's only ever set on construction
        $em->getConnection()->executeStatement(
            "UPDATE credit_requests SET approval_limit_date = NOW() - INTERVAL '16 days' WHERE id = :id",
            ['id' => $expiredCreditRequest->getId()],
        );
        $em->clear();

        $application = new Application(self::$kernel);
        $command = $application->find('app:credit-request:delete-expired-proposals');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted 1 expired credit request proposal', $commandTester->getDisplay());

        self::assertNull($em->find(CreditRequest::class, $expiredCreditRequest->getId()));
        self::assertNull($em->find(Installment::class, $installment->getId()));
    }

    public function testKeepsExpiredProposalsWithinTheirRetentionPeriod(): void
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

        $recentlyExpiredCreditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::ProposalExpired,
        );
        $em->persist($recentlyExpiredCreditRequest);
        $em->flush();
        $em->clear();

        $application = new Application(self::$kernel);
        $command = $application->find('app:credit-request:delete-expired-proposals');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted 0 expired credit request proposal', $commandTester->getDisplay());

        self::assertNotNull($em->find(CreditRequest::class, $recentlyExpiredCreditRequest->getId()));
    }
}
