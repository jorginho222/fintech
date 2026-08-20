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

        $client->jsonRequest('POST', self::APPLY_URL, [
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ], self::bearer($token));

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('evaluation_pending', $body['status']);
        self::assertSame($company['id'], $body['company']['id']);
        self::assertSame(10_000_000, (int) $body['amount']);
        self::assertSame(12, $body['installmentQuantity']);
    }
}
