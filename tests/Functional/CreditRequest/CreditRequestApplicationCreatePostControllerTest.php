<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestApplicationCreatePostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string APPLY_URL = '/api/v1/credit-request/apply';

    public function testApplyReturns201WithPendingApplication(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $before = new \DateTimeImmutable('-1 second');

        $client->jsonRequest('POST', self::APPLY_URL, [
            'reference'           => 'Ampliación planta',
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ], self::bearer($token));

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('evaluation_pending', $body['status']);
        self::assertSame('Ampliación planta', $body['reference']);
        $createdAt = new \DateTimeImmutable($body['createdAt']);
        self::assertGreaterThanOrEqual($before, $createdAt);
        self::assertLessThanOrEqual(new \DateTimeImmutable('+1 second'), $createdAt);
        self::assertNull($body['creditRequest']);
        self::assertSame($company['id'], $body['company']['id']);
        self::assertSame(10_000_000, (int) $body['amount']);
        self::assertSame(12, $body['installmentQuantity']);
    }

    public function testApplyReturns409WhenCompanyAlreadyUsedTheReference(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $payload = [
            'reference'           => 'Capital de trabajo',
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ];
        $client->jsonRequest('POST', self::APPLY_URL, $payload, self::bearer($token));
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', self::APPLY_URL, $payload, self::bearer($token));

        self::assertResponseStatusCodeSame(409);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Ya existe una solicitud de crédito con esa referencia.', $body['error']);
    }

    public function testApplyReturns422WhenReferenceIsMissing(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('POST', self::APPLY_URL, [
            'reference'           => '   ',
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ], self::bearer($token));

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('reference', $body['errors']);
    }

    public function testApplyReturns422WhenReferenceIsLongerThan60Characters(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('POST', self::APPLY_URL, [
            'reference'           => str_repeat('a', 61),
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ], self::bearer($token));

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('reference', $body['errors']);
    }
}
