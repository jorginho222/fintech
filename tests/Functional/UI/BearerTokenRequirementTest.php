<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Company\Domain\Model\Company;
use App\Company\Infrastructure\Security\CompanyUser;
use App\CreditRequest\Infrastructure\Security\CreditBureauWebhookAuthenticator;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards the firewall wiring itself: which endpoints demand a bearer token and which do not.
 */
final class BearerTokenRequirementTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string APPLY_URL   = '/api/v1/credit-request/apply';
    private const string WEBHOOK_URL = '/api/v1/credit-request-application-update';
    private const string REFRESH_URL = '/api/v1/refresh';

    public function testProtectedEndpointWithoutATokenReturns401(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', self::APPLY_URL, [
            'amount'              => 10_000_000,
            'installmentQuantity' => 12,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithAnInvalidTokenReturns401(): void
    {
        $client = static::createClient();

        $client->jsonRequest(
            'POST',
            self::APPLY_URL,
            ['amount' => 10_000_000, 'installmentQuantity' => 12],
            self::bearer('garbage.token.value'),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithAValidTokenIsReachable(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest(
            'POST',
            self::APPLY_URL,
            ['amount' => 10_000_000, 'installmentQuantity' => 12],
            self::bearer($token),
        );

        self::assertResponseStatusCodeSame(201);
    }

    public function testRegistrationStaysPublic(): void
    {
        $client = static::createClient();
        $this->registerCompany($client);

        self::assertResponseStatusCodeSame(201);
    }

    public function testRefreshTokenCannotBeUsedAsABearerCredential(): void
    {
        $client = static::createClient();
        [, , $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest(
            'POST',
            self::APPLY_URL,
            ['amount' => 10_000_000, 'installmentQuantity' => 12],
            self::bearer($refreshToken),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnAccessTokenCannotBeUsedAtTheRefreshEndpoint(): void
    {
        $client = static::createClient();
        [, $accessToken] = $this->registerAndLogin($client);

        $client->jsonRequest('POST', self::REFRESH_URL, ['refreshToken' => $accessToken]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshIsReachableWhileCarryingAnInvalidBearerToken(): void
    {
        $client = static::createClient();
        [, , $refreshToken] = $this->registerAndLoginWithTokens($client);

        $client->jsonRequest(
            'POST',
            self::REFRESH_URL,
            ['refreshToken' => $refreshToken],
            self::bearer('garbage.token.value'),
        );

        self::assertResponseIsSuccessful();
    }

    public function testAnExpiredAccessTokenReturns401(): void
    {
        $client = static::createClient();
        $expiredAccessToken = $this->mintToken($client, 'access', time() - 10);

        $client->jsonRequest(
            'POST',
            self::APPLY_URL,
            ['amount' => 10_000_000, 'installmentQuantity' => 12],
            self::bearer($expiredAccessToken),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testWebhookWithoutTheSharedSecretReturns401(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', self::WEBHOOK_URL, ['applicationId' => 'whatever']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testWebhookRejectsACompanyBearerToken(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('PUT', self::WEBHOOK_URL, ['applicationId' => 'whatever'], self::bearer($token));

        self::assertResponseStatusCodeSame(401);
    }

    public function testWebhookWithTheSharedSecretIsReachable(): void
    {
        $client = static::createClient();
        $secret = self::getContainer()->getParameter('app.credit_bureau_simulation.webhook_secret');

        $client->jsonRequest('PUT', self::WEBHOOK_URL, ['applicationId' => 'whatever'], [
            'HTTP_' . str_replace('-', '_', strtoupper(CreditBureauWebhookAuthenticator::HEADER)) => $secret,
        ]);

        // It gets past the firewall and fails validation on the payload instead of on authentication.
        self::assertResponseStatusCodeSame(422);
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
