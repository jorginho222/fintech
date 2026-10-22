<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PermissionEnforcementTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    public function testAMemberReachesOnlyTheResourcesItWasGranted(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $memberToken = $this->createMemberAndLogin($client, $adminToken, ['total_debt.view']);

        $this->requestTotalDebt($client, $memberToken);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($memberToken));
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'No tiene permiso para acceder a este recurso.'], json_decode($client->getResponse()->getContent(), true));

        $client->jsonRequest('PUT', '/api/v1/installment/eeeeeeee-eeee-4eee-eeee-eeeeeeeeeeee/pay', [], self::bearer($memberToken));
        self::assertResponseStatusCodeSame(403);
    }

    public function testRemovingAPermissionAppliesToTheMembersNextRequest(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $memberToken = $this->createMemberAndLogin($client, $adminToken, ['total_debt.view']);

        $this->requestTotalDebt($client, $memberToken);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', '/api/v1/user/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb/permissions', ['permissions' => []], self::bearer($adminToken));
        self::assertResponseIsSuccessful();

        // Same, still-valid token: the user is reloaded per request, so the removal applies at once.
        $this->requestTotalDebt($client, $memberToken);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAdminKeepsFullAccess(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $this->requestTotalDebt($client, $adminToken);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
    }

    private function requestTotalDebt(KernelBrowser $client, string $token): void
    {
        $today = new \DateTimeImmutable('today');

        $client->jsonRequest('GET', '/api/v1/company-total-debt?' . http_build_query([
            'month' => (int) $today->format('n'),
            'year'  => (int) $today->format('Y'),
        ]), [], self::bearer($token));
    }
}
