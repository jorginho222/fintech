<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class ExpireCreditRequestProposalsCommandTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testExpiresProposalsPastTheirApprovalLimitDate(): void
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

        $expiredCreditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Proposal,
        );
        $em->persist($expiredCreditRequest);
        $em->flush();

        // backdate the approval limit date directly, since the domain model only sets it on construction
        $em->getConnection()->executeStatement(
            "UPDATE credit_requests SET approval_limit_date = NOW() - INTERVAL '1 day' WHERE id = :id",
            ['id' => $expiredCreditRequest->getId()],
        );
        $em->clear();

        $application = new Application(self::$kernel);
        $command = $application->find('app:credit-request:expire-proposals');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Expired 1 credit request proposal', $commandTester->getDisplay());

        $refreshedCreditRequest = $em->find(CreditRequest::class, $expiredCreditRequest->getId());
        self::assertSame(CreditRequestStatus::ProposalExpired, $refreshedCreditRequest->getStatus());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'empresa@example.com');
        self::assertEmailTextBodyContains($email, 'Empresa SRL');
    }
}
