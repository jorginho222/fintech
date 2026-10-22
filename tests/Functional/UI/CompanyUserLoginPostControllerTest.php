<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyUserLoginPostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    public function testLoginWithValidCredentialsReturnsAnAccessTokenARefreshTokenAndTheCompany(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'taxIdentifier' => $company['cuit'],
            'password'      => $company['password'],
        ]);

        self::assertResponseIsSuccessful();

        $body = json_decode($client->getResponse()->getContent(), true);

        foreach (['accessToken', 'refreshToken'] as $key) {
            self::assertNotEmpty($body[$key]['token']);
            // A JWT is three dot-separated segments.
            self::assertCount(3, explode('.', $body[$key]['token']));
            self::assertNotEmpty($body[$key]['expiresAt']);
            self::assertGreaterThan(new \DateTimeImmutable(), new \DateTimeImmutable($body[$key]['expiresAt']));
        }

        self::assertNotSame($body['accessToken']['token'], $body['refreshToken']['token']);
        self::assertGreaterThan(
            new \DateTimeImmutable($body['accessToken']['expiresAt']),
            new \DateTimeImmutable($body['refreshToken']['expiresAt']),
        );

        self::assertSame($company['id'], $body['company']['id']);
        self::assertSame($company['cuit'], $body['company']['cuit']);
        self::assertSame($company['socialReason'], $body['company']['socialReason']);
        self::assertSame($company['taxStatus'], $body['company']['taxStatus']);
        self::assertArrayNotHasKey('password', $body['company']);

        self::assertSame('admin', $body['user']['type']);
        self::assertSame($company['cuit'], $body['user']['taxIdentifier']);
        self::assertFalse($body['user']['mustChangePassword']);
        self::assertCount(8, $body['user']['permissions']);
    }

    public function testAMemberLogsInWithItsCuilAndGetsItsOwnCompanyAndPermissions(): void
    {
        $client = static::createClient();
        [$company, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken, ['permissions' => ['payment_calendar.view']]);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'taxIdentifier' => $member['taxIdentifier'],
            'password'      => $member['password'],
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($company['id'], $body['company']['id']);
        self::assertSame('member', $body['user']['type']);
        self::assertSame(['payment_calendar.view'], $body['user']['permissions']);
    }

    public function testTheFormerCuitLoginKeyIsNoLongerAccepted(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, ['cuit' => $company['cuit'], 'password' => $company['password']]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('taxIdentifier', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest('POST', self::LOGIN_URL, [
            'taxIdentifier' => $company['cuit'],
            'password'      => 'wrongpassword1',
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
            'taxIdentifier' => '20999999999',
            'password'      => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid credentials.',
            json_decode($client->getResponse()->getContent(), true)['error'],
        );
    }

    public function testLoginWithMalformedTaxIdentifierReturns422(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => 'not-a-cuit', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('taxIdentifier', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testLoginIsReachableWhileCarryingAnInvalidToken(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $client->jsonRequest(
            'POST',
            self::LOGIN_URL,
            ['taxIdentifier' => $company['cuit'], 'password' => $company['password']],
            self::bearer('garbage.token.value'),
        );

        self::assertResponseIsSuccessful();
    }
}
