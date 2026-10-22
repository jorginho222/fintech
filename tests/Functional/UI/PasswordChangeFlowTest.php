<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasswordChangeFlowTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string PASSWORD_URL = '/api/v1/me/password';

    public function testAMemberMustChangeItsPasswordBeforeReachingAnythingElse(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken, ['permissions' => ['credit_request.list']]);
        $token  = $this->login($client, $member['taxIdentifier'], $member['password']);

        // Blocked even though the permission is granted.
        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($token));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Debe cambiar su contraseña para continuar.', json_decode($client->getResponse()->getContent(), true)['error']);

        $client->jsonRequest('GET', '/api/v1/me', [], self::bearer($token));
        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode($client->getResponse()->getContent(), true)['mustChangePassword']);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => $member['password'], 'newPassword' => 'Changed456?'], self::bearer($token));
        self::assertResponseIsSuccessful();
        self::assertFalse(json_decode($client->getResponse()->getContent(), true)['mustChangePassword']);

        // Same token, normal access from the next request on.
        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($token));
        self::assertResponseIsSuccessful();

        // The old password no longer works; the new one does.
        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => $member['taxIdentifier'], 'password' => $member['password']]);
        self::assertResponseStatusCodeSame(401);
        $this->login($client, $member['taxIdentifier'], 'Changed456?');
        self::assertResponseIsSuccessful();
    }

    public function testAWrongCurrentPasswordOrAReusedOneIsRejected(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken);
        $token  = $this->login($client, $member['taxIdentifier'], $member['password']);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => 'Wrong123!', 'newPassword' => 'Changed456?'], self::bearer($token));
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => $member['password'], 'newPassword' => $member['password']], self::bearer($token));
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => $member['password'], 'newPassword' => 'nonumber!A'], self::bearer($token));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('newPassword', json_decode($client->getResponse()->getContent(), true)['errors']);
    }

    public function testThePasswordCanOnlyBeChangedOnceAndNeverByTheAdmin(): void
    {
        $client = static::createClient();
        [$company, $adminToken] = $this->registerAndLogin($client);
        $memberToken = $this->createMemberAndLogin($client, $adminToken, []);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => 'Changed456?', 'newPassword' => 'Another789#'], self::bearer($memberToken));
        self::assertResponseStatusCodeSame(409);

        $client->jsonRequest('PUT', self::PASSWORD_URL, ['currentPassword' => $company['password'], 'newPassword' => 'Another789#'], self::bearer($adminToken));
        self::assertResponseStatusCodeSame(409);
    }
}
