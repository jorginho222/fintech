<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditRequestApplyPostControllerTest extends WebTestCase
{
    private const string APPLY_URL    = '/api/credit-request/apply';
    private const string COMPANY_URL  = '/api/company';

    private const array COMPANY = [
        'id'           => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
        'socialReason' => 'Empresa SRL',
        'cuit'         => '20123456780',
        'email'        => 'empresa@empresa.com',
        'taxStatus'    => 'monotributo',
    ];

    public function testApplyReturns201WithPendingApplication(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::COMPANY_URL, self::COMPANY);

        $client->jsonRequest('POST', self::APPLY_URL, [
            'companyId'           => self::COMPANY['id'],
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('evaluation_pending', $body['status']);
        self::assertSame(self::COMPANY['id'], $body['company']['id']);
        self::assertSame(10_000_000, (int) $body['amount']);
        self::assertSame(12, $body['installmentQuantity']);
    }
}
