<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Service\PasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyRegistrationPostControllerTest extends WebTestCase
{
    private const string BASE_URL = '/api/v1/company_registration';

    private const array VALID_PAYLOAD = [
        'id'           => '550e8400-e29b-41d4-a716-446655440000',
        'socialReason' => 'Mi Empresa SRL',
        'cuit'         => '20123456789',
        'email'        => 'contacto@empresa.com',
        'taxStatus'    => 'monotributo',
        'password'     => 'Secret123!',
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

    public function testCreateNeverEchoesThePassword(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        self::assertArrayNotHasKey('password', json_decode($client->getResponse()->getContent(), true));
        self::assertStringNotContainsString(
            self::VALID_PAYLOAD['password'],
            $client->getResponse()->getContent(),
        );
    }

    public function testCreateStoresThePasswordHashedAndNotInPlainText(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);

        $storedPassword = self::getContainer()
            ->get(EntityManagerInterface::class)
            ->find(Company::class, self::VALID_PAYLOAD['id'])
            ->getHashedPassword();

        self::assertNotSame(self::VALID_PAYLOAD['password'], $storedPassword);
        self::assertTrue(
            self::getContainer()->get(PasswordHasherInterface::class)
                ->verify($storedPassword, self::VALID_PAYLOAD['password']),
        );
    }

    public function testCreateWithoutPasswordReturns422(): void
    {
        $client = static::createClient();
        $payload = self::VALID_PAYLOAD;
        unset($payload['password']);

        $client->jsonRequest('POST', self::BASE_URL, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testCreateWithTooShortPasswordReturns422(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, [...self::VALID_PAYLOAD, 'password' => 'ab1']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testCreateWithPasswordMissingACapitalLetterReturns422(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, [...self::VALID_PAYLOAD, 'password' => 'secret123!']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testCreateWithPasswordMissingASpecialCharacterReturns422(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, [...self::VALID_PAYLOAD, 'password' => 'Secret123']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testCreateWithAnAlreadyRegisteredCuitReturns409(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', self::BASE_URL, self::VALID_PAYLOAD);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', self::BASE_URL, [
            ...self::VALID_PAYLOAD,
            'id'    => '660e8400-e29b-41d4-a716-446655440001',
            'email' => 'otro@empresa.com',
        ]);

        self::assertResponseStatusCodeSame(409);
    }
}
