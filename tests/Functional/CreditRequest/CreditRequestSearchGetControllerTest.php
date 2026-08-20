<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestSearchGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string SEARCH_URL = '/api/v1/credit-request/search';

    public function testSearchReturnsOnlyActiveCreditRequestsOwnedByTheAuthenticatedCompany(): void
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
            6,
            $firstCompany,
            CreditRequestStatus::Active,
        );
        $em->persist($activeCreditRequest);
        $em->persist(new CreditRequest(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            '200000',
            '50',
            12,
            $firstCompany,
            CreditRequestStatus::Proposal,
        ));
        $em->persist(new CreditRequest(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            '300000',
            '45',
            18,
            $secondCompany,
            CreditRequestStatus::Active,
        ));
        $em->flush();

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($firstToken));

        self::assertResponseIsSuccessful();
        $creditRequests = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $creditRequests);
        self::assertSame($activeCreditRequest->getId(), $creditRequests[0]['id']);
        self::assertSame('active', $creditRequests[0]['status']);
        self::assertSame($firstCompanyPayload['id'], $creditRequests[0]['company']['id']);
    }
}
