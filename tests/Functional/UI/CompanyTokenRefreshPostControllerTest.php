<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Company\Domain\Model\Company;
use App\Company\Infrastructure\Security\CompanyUser;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompanyTokenRefreshPostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string REFRESH_URL = '/api/v1/refresh';
    private const string SEARCH_URL  = '/api/v1/credit-request/search';

    public function testARefreshTokenIssuesANewAccessAndRefreshTokenPair(): void
    {
        $client = static::createClient();
        [, , $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);

        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(3, explode('.', $body['accessToken']['token']));
        self::assertCount(3, explode('.', $body['refreshToken']['token']));
        self::assertNotSame($refreshToken, $body['refreshToken']['token']);
    }

    public function testTheNewAccessTokenWorksAgainstAProtectedEndpoint(): void
    {
        $client = static::createClient();
        [, , $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);
        $newAccessToken = json_decode($client->getResponse()->getContent(), true)['accessToken']['token'];

        $client->jsonRequest('GET', self::SEARCH_URL, [], self::bearer($newAccessToken));

        self::assertResponseIsSuccessful();
    }

    public function testARefreshTokenIsSingleUseAndCannotBeReplayed(): void
    {
        $client = static::createClient();
        [, , $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid refresh token.',
            json_decode($client->getResponse()->getContent(), true)['error'],
        );
    }

    public function testARevokedRefreshTokenCannotBeUsedToRefresh(): void
    {
        $client = static::createClient();
        [, $accessToken, $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest('POST', '/api/v1/logout', ['refreshToken' => $refreshToken], self::bearer($accessToken));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $refreshToken]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAGarbageRefreshTokenReturns401(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => 'garbage.token.value']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAMissingRefreshTokenReturns422(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::REFRESH_URL, []);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnExpiredRefreshTokenReturns401(): void
    {
        $client = static::createClient();
        $expiredRefreshToken = $this->mintToken($client, 'refresh', time() - 10);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $expiredRefreshToken]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid refresh token.',
            json_decode($client->getResponse()->getContent(), true)['error'],
        );
    }

    /**
     * Mints a token with an arbitrary type/expiration, bypassing the configured TTLs, so
     * expiration behavior can be verified without waiting for a real token to expire.
     */
    private function mintToken(KernelBrowser $client, string $type, int $exp): string
    {
        $companyPayload = $this->registerCompany($client);

        $em      = self::getContainer()->get(EntityManagerInterface::class);
        $company = $em->find(Company::class, $companyPayload['id']);

        $jwtTokenManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwtTokenManager->createFromPayload(CompanyUser::fromCompany($company), [
            'companyId' => $company->getId(),
            'type'      => $type,
            'jti'       => 'test-jti-' . $type . '-' . $exp,
            'exp'       => $exp,
        ]);
    }
}
