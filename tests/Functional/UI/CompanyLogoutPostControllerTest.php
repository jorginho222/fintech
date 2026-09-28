<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyLogoutPostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string LOGOUT_URL  = '/api/v1/logout';
    private const string REFRESH_URL = '/api/v1/refresh';
    private const string SEARCH_URL  = '/api/v1/credit-request/search';

    public function testLogoutWithAValidTokenReturns204(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('POST', self::LOGOUT_URL, [], self::bearer($token));

        self::assertResponseStatusCodeSame(204);
    }

    public function testLogoutWithoutATokenReturns401(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::LOGOUT_URL);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheRevokedAccessTokenCanNoLongerAccessProtectedEndpoints(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('POST', self::LOGOUT_URL, [], self::bearer($token));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($token));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherTokensForTheSameCompanyStayValidAfterALogout(): void
    {
        $client  = static::createClient();
        $company = $this->registerCompany($client);

        $firstToken  = $this->login($client, $company['cuit'], $company['password']);
        $secondToken = $this->login($client, $company['cuit'], $company['password']);

        $client->jsonRequest('POST', self::LOGOUT_URL, [], self::bearer($firstToken));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($secondToken));

        self::assertResponseIsSuccessful();
    }

    public function testLogoutWithARefreshTokenInTheBodyRevokesTheWholeSession(): void
    {
        $client = static::createClient();
        [, $accessToken, $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::LOGOUT_URL, ['refreshToken' => $refreshToken], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(204);

        // The access token no longer works...
        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(401);

        // ...and neither does the refresh token.
        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithoutARefreshTokenInTheBodyLeavesItUsable(): void
    {
        $client = static::createClient();
        [, $accessToken, $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::LOGOUT_URL, [], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);
        self::assertResponseIsSuccessful();
    }

    public function testLogoutWithAGarbageRefreshTokenInTheBodyStillRevokesTheAccessToken(): void
    {
        $client = static::createClient();
        [, $accessToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::LOGOUT_URL, ['refreshToken' => 'garbage.token.value'], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(401);
    }
}
