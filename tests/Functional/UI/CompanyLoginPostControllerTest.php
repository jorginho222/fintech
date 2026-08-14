<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyLoginPostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    public function testLoginWithValidCredentialsReturnsATokenAndTheCompany(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'cuit'     => $company['cuit'],
            'password' => $company['password'],
        ]);

        self::assertResponseIsSuccessful();

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertNotEmpty($body['token']);
        // A JWT is three dot-separated segments.
        self::assertCount(3, explode('.', $body['token']));
        self::assertNotEmpty($body['expiresAt']);
        self::assertGreaterThan(new \DateTimeImmutable(), new \DateTimeImmutable($body['expiresAt']));
        self::assertSame($company['id'], $body['company']['id']);
        self::assertSame($company['cuit'], $body['company']['cuit']);
        self::assertSame($company['socialReason'], $body['company']['socialReason']);
        self::assertSame($company['taxStatus'], $body['company']['taxStatus']);
        self::assertArrayNotHasKey('password', $body['company']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'cuit'     => $company['cuit'],
            'password' => 'wrongpassword1',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid credentials.',
            json_decode($client->getResponse()->getContent(), true)['error'],
        );
    }

    public function testLoginWithUnknownCuitReturns401WithTheSameErrorAsAWrongPassword(): void
    {
        $client = static::createClient();
        $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'cuit'     => '20999999999',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid credentials.',
            json_decode($client->getResponse()->getContent(), true)['error'],
        );
    }

    public function testLoginWithMalformedCuitReturns422(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::LOGIN_URL, ['cuit' => 'not-a-cuit', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('cuit', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testLoginIsReachableWhileCarryingAnInvalidToken(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest(
            'POST',
            self::LOGIN_URL,
            ['cuit' => $company['cuit'], 'password' => $company['password']],
            self::bearer('garbage.token.value'),
        );

        self::assertResponseIsSuccessful();
    }
}
