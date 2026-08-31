<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InstallmentPendingToPaySearchGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string SEARCH_URL = '/api/v1/installment/pending-to-pay/search';

    public function testSearchReturnsPendingAndOverdueInstallmentsDueWithinTheQueriedMonth(): void
    {
        $client = static::createClient();
        [$firstCompanyPayload, $firstToken] = $this->registerAndLogin($client);
        [$secondCompanyPayload] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $firstCompany = $em->find(Company::class, $firstCompanyPayload['id']);
        $secondCompany = $em->find(Company::class, $secondCompanyPayload['id']);

        $activeCreditRequest = new CreditRequest(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            '100000',
            '55',
            3,
            $firstCompany,
            CreditRequestStatus::Active,
        );
        $em->persist($activeCreditRequest);

        $dueInMonth = $this->createInstallment('11111111-1111-4111-8111-111111111111', 1, InstallmentStatus::Pending, $activeCreditRequest, new \DateTimeImmutable('2026-06-15'));
        $overdueInMonth = $this->createInstallment('22222222-2222-4222-8222-222222222222', 2, InstallmentStatus::Overdue, $activeCreditRequest, new \DateTimeImmutable('2026-06-10'));
        $overdueFromPreviousMonth = $this->createInstallment('77777777-7777-4777-8777-777777777777', 5, InstallmentStatus::Overdue, $activeCreditRequest, new \DateTimeImmutable('2026-05-10'));
        $pendingNextMonth = $this->createInstallment('33333333-3333-4333-8333-333333333333', 3, InstallmentStatus::Pending, $activeCreditRequest, new \DateTimeImmutable('2026-07-15'));
        $paidInMonth = $this->createInstallment('44444444-4444-4444-8444-444444444444', 4, InstallmentStatus::Paid, $activeCreditRequest, new \DateTimeImmutable('2026-06-20'));
        $em->persist($dueInMonth);
        $em->persist($overdueInMonth);
        $em->persist($overdueFromPreviousMonth);
        $em->persist($pendingNextMonth);
        $em->persist($paidInMonth);

        $proposalCreditRequest = new CreditRequest(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            '200000',
            '50',
            1,
            $firstCompany,
            CreditRequestStatus::Proposal,
        );
        $em->persist($proposalCreditRequest);
        $pendingOnProposal = $this->createInstallment('55555555-5555-4555-8555-555555555555', 1, InstallmentStatus::Pending, $proposalCreditRequest, new \DateTimeImmutable('2026-06-18'));
        $em->persist($pendingOnProposal);

        $otherCompanyCreditRequest = new CreditRequest(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            '300000',
            '45',
            1,
            $secondCompany,
            CreditRequestStatus::Active,
        );
        $em->persist($otherCompanyCreditRequest);
        $pendingOnOtherCompany = $this->createInstallment('66666666-6666-4666-8666-666666666666', 1, InstallmentStatus::Pending, $otherCompanyCreditRequest, new \DateTimeImmutable('2026-06-18'));
        $em->persist($pendingOnOtherCompany);

        $em->flush();

        $client->jsonRequest('GET', self::SEARCH_URL . '?year=2026&month=6', [], self::bearer($firstToken));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $installments = $response['installments'];
        $installmentIds = array_column($installments, 'id');

        self::assertCount(2, $installments);
        self::assertContains($dueInMonth->getId(), $installmentIds);
        self::assertContains($overdueInMonth->getId(), $installmentIds);
        self::assertNotContains($overdueFromPreviousMonth->getId(), $installmentIds);
        self::assertSame('1787666.66', $response['totalAmount']);
    }

    private function createInstallment(
        string $id,
        int $periodNumber,
        InstallmentStatus $status,
        CreditRequest $creditRequest,
        \DateTimeImmutable $dueDate,
    ): Installment {
        $installment = new Installment(
            $id,
            $periodNumber,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.33',
            $status,
            $creditRequest,
        );
        $installment->changeDueDate($dueDate);

        return $installment;
    }
}
