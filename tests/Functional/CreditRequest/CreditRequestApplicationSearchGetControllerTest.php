<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestApplicationSearchGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string APPLY_URL  = '/api/v1/credit-request/apply';
    private const string SEARCH_URL = '/api/v1/credit-request-application/search';

    public function testSearchReturnsOnlyApplicationsOwnedByTheAuthenticatedCompany(): void
    {
        $client = static::createClient();

        [$firstCompany, $firstToken] = $this->registerAndLogin($client);
        $firstApplication = $this->createApplication($client, $firstCompany['id'], $firstToken);

        [$secondCompany, $secondToken] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);
        $secondApplication = $this->createApplication($client, $secondCompany['id'], $secondToken);

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($firstToken));

        self::assertResponseIsSuccessful();
        $applications = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $applications);
        self::assertSame($firstApplication['id'], $applications[0]['id']);
        self::assertSame($firstCompany['id'], $applications[0]['company']['id']);
        self::assertNotSame($secondApplication['id'], $applications[0]['id']);
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
