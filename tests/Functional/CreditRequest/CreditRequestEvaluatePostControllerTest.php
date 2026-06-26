<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestEvaluatePostControllerTest extends WebTestCase
{
    private const string EVALUATE_URL = '/api/credit-request/evaluate';
    private const string COMPANY_URL  = '/api/company';

    // last digit 0 → even → passes score check
    private const array EVEN_CUIT_COMPANY = [
        'id'           => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
        'socialReason' => 'Empresa Par SRL',
        'cuit'         => '20123456780',
        'email'        => 'par@empresa.com',
        'taxStatus'    => 'monotributo',
    ];

    // last digit 1 → odd → fails score check
    private const array ODD_CUIT_COMPANY = [
        'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
        'socialReason' => 'Empresa Impar SRL',
        'cuit'         => '20123456781',
        'email'        => 'impar@empresa.com',
        'taxStatus'    => 'monotributo',
    ];

    public function testApprovedReturns200(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::COMPANY_URL, self::EVEN_CUIT_COMPANY);

        $client->jsonRequest('POST', self::EVALUATE_URL, [
            'companyId'           => self::EVEN_CUIT_COMPANY['id'],
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ]);

        self::assertResponseStatusCodeSame(200);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('approved', $body['status']);
    }

    public function testInsufficientScoreReturns422(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::COMPANY_URL, self::ODD_CUIT_COMPANY);

        $client->jsonRequest('POST', self::EVALUATE_URL, [
            'companyId'           => self::ODD_CUIT_COMPANY['id'],
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }

    public function testExceededAmountReturns422(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::COMPANY_URL, self::EVEN_CUIT_COMPANY);

        $client->jsonRequest('POST', self::EVALUATE_URL, [
            'companyId'           => self::EVEN_CUIT_COMPANY['id'],
            'amount'              => 50_000_001,
            'installmentQuantity' => 12,
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }
}
