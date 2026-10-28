<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestApplicationStatsGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string APPLY_URL = '/api/v1/credit-request/apply';
    private const string STATS_URL = '/api/v1/credit-request-application/stats';

    public function testReturnsTheTotalAndTheCountPerStatus(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);

        $approved       = $this->createApplication($client, $company['id'], $token);
        $rejectedFirst  = $this->createApplication($client, $company['id'], $token);
        $rejectedSecond = $this->createApplication($client, $company['id'], $token);
        $this->createApplication($client, $company['id'], $token);
        $this->createApplication($client, $company['id'], $token);
        $this->createApplication($client, $company['id'], $token);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->find(CreditRequestApplication::class, $approved['id'])->approve();
        $em->find(CreditRequestApplication::class, $rejectedFirst['id'])->reject('CAPACIDAD_PAGO_EXCEDIDA');
        $em->find(CreditRequestApplication::class, $rejectedSecond['id'])->reject('CAPACIDAD_PAGO_EXCEDIDA');
        $em->flush();

        self::assertSame(
            ['total' => 6, 'approved' => 1, 'rejected' => 2, 'evaluationPending' => 3],
            $this->requestStats($client, $token),
        );
    }

    public function testReturnsZeroCountsWhenTheCompanyHasNoApplications(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        self::assertSame(
            ['total' => 0, 'approved' => 0, 'rejected' => 0, 'evaluationPending' => 0],
            $this->requestStats($client, $token),
        );
    }

    public function testExcludesApplicationsOfOtherCompanies(): void
    {
        $client = static::createClient();
        [$firstCompany, $firstToken] = $this->registerAndLogin($client);
        $this->createApplication($client, $firstCompany['id'], $firstToken);

        [$secondCompany, $secondToken] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);
        $this->createApplication($client, $secondCompany['id'], $secondToken);
        $this->createApplication($client, $secondCompany['id'], $secondToken);

        self::assertSame(
            ['total' => 1, 'approved' => 0, 'rejected' => 0, 'evaluationPending' => 1],
            $this->requestStats($client, $firstToken),
        );
    }

    public function testRequiresTheCreditRequestApplicationListPermission(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $deniedToken = $this->createMemberAndLogin($client, $adminToken, ['total_debt.view']);
        $client->jsonRequest('GET', self::STATS_URL, [], self::bearer($deniedToken));
        self::assertResponseStatusCodeSame(403);

        $allowedToken = $this->createMemberAndLogin($client, $adminToken, ['credit_request_application.list'], [
            'id'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'taxIdentifier' => '27666777888',
        ]);
        $client->jsonRequest('GET', self::STATS_URL, [], self::bearer($allowedToken));
        self::assertResponseIsSuccessful();
    }

    public function testRejectsUnauthenticatedRequests(): void
    {
        $client = static::createClient();

        $client->jsonRequest('GET', self::STATS_URL);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, int>
     */
    private function requestStats(KernelBrowser $client, string $token): array
    {
        $client->jsonRequest('GET', self::STATS_URL, [], self::bearer($token));

        self::assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function createApplication(
        KernelBrowser $client,
        string $companyId,
        string $token,
    ): array {
        $client->jsonRequest('POST', self::APPLY_URL, [
            'companyId'           => $companyId,
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ], self::bearer($token));

        self::assertResponseStatusCodeSame(201);

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
