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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestInProgressStatsGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string URL = '/api/v1/credit-request/stats';

    public function testPoolsTheInstallmentsOfEveryActiveCreditRequest(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);

        // Pooled: 3 paid of 8 installments = 37.5%. Averaging per credit would give (100% + 16.67%) / 2 = 58.33%.
        $this->creditRequest('10000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
            InstallmentStatus::Paid,
        ]);
        $this->creditRequest('20000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
            InstallmentStatus::Overdue,
            InstallmentStatus::Overdue,
            InstallmentStatus::Pending,
            InstallmentStatus::Pending,
            InstallmentStatus::Pending,
        ]);

        self::assertSame(['total' => 2, 'paidPercentage' => 37.5], $this->requestStats($client, $token));
    }

    public function testIgnoresCreditRequestsThatAreNotActive(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);

        $this->creditRequest('10000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
            InstallmentStatus::Pending,
            InstallmentStatus::Pending,
        ]);
        $this->creditRequest('20000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Proposal, [
            InstallmentStatus::Pending,
        ]);
        $this->creditRequest('30000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::ProposalExpired, [
            InstallmentStatus::Pending,
        ]);
        $this->creditRequest('40000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Paid, [
            InstallmentStatus::Paid,
            InstallmentStatus::Paid,
        ]);

        self::assertSame(['total' => 1, 'paidPercentage' => 33.33], $this->requestStats($client, $token));
    }

    public function testReturnsANullPercentageWhenThereAreNoActiveCreditRequests(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $this->creditRequest('10000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Paid, [
            InstallmentStatus::Paid,
        ]);

        self::assertSame(['total' => 0, 'paidPercentage' => null], $this->requestStats($client, $token));
    }

    public function testExcludesCreditRequestsOfOtherCompanies(): void
    {
        $client = static::createClient();
        [$firstCompany, $firstToken] = $this->registerAndLogin($client);
        $this->creditRequest('10000000-0000-4000-8000-000000000000', $firstCompany['id'], CreditRequestStatus::Active, [
            InstallmentStatus::Pending,
        ]);

        [$secondCompany] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);
        $this->creditRequest('20000000-0000-4000-8000-000000000000', $secondCompany['id'], CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
        ]);

        self::assertSame(['total' => 1, 'paidPercentage' => 0.0], $this->requestStats($client, $firstToken));
    }

    public function testRequiresTheCreditRequestListPermission(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $deniedToken = $this->createMemberAndLogin($client, $adminToken, ['credit_request_application.list']);
        $client->jsonRequest('GET', self::URL, [], self::bearer($deniedToken));
        self::assertResponseStatusCodeSame(403);

        $allowedToken = $this->createMemberAndLogin($client, $adminToken, ['credit_request.list'], [
            'id'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'taxIdentifier' => '27666777888',
        ]);
        $client->jsonRequest('GET', self::URL, [], self::bearer($allowedToken));
        self::assertResponseIsSuccessful();
    }

    public function testRejectsUnauthenticatedRequests(): void
    {
        $client = static::createClient();

        $client->jsonRequest('GET', self::URL);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestStats(KernelBrowser $client, string $token): array
    {
        $client->jsonRequest('GET', self::URL, [], self::bearer($token));

        self::assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param InstallmentStatus[] $installmentStatuses
     */
    private function creditRequest(string $id, string $companyId, CreditRequestStatus $status, array $installmentStatuses): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $creditRequest = new CreditRequest($id, '100000', '55', count($installmentStatuses), $em->find(Company::class, $companyId), $status);
        $em->persist($creditRequest);

        foreach ($installmentStatuses as $index => $installmentStatus) {
            $em->persist(new Installment(
                substr($id, 0, -12) . sprintf('%012d', $index + 1),
                $index + 1,
                '1000.00',
                '0.00',
                '0.00',
                '1000.00',
                $installmentStatus,
                $creditRequest,
            ));
        }

        $em->flush();
    }
}
