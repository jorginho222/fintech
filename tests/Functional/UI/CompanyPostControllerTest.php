<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyPostControllerTest extends WebTestCase
{
    private const string BASE_URL = '/api/company';

    private const array VALID_PAYLOAD = [
        'id'           => '550e8400-e29b-41d4-a716-446655440000',
        'socialReason' => 'Mi Empresa SRL',
        'cuit'         => '20123456789',
        'email'        => 'contacto@empresa.com',
        'taxStatus'    => 'monotributo',
    ];

    public function testCreateReturns201WithCreatedResource(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(self::VALID_PAYLOAD['id'], $body['id']);
        self::assertSame(self::VALID_PAYLOAD['socialReason'], $body['socialReason']);
        self::assertSame(self::VALID_PAYLOAD['cuit'], $body['cuit']);
        self::assertSame(self::VALID_PAYLOAD['email'], $body['email']);
        self::assertSame(self::VALID_PAYLOAD['taxStatus'], $body['taxStatus']);
    }

}
