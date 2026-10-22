<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberUserDeactivationTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string MEMBER_URL = '/api/v1/user/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    public function testADeactivatedMemberLosesAccessUntilActivatedAgain(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $memberToken = $this->createMemberAndLogin($client, $adminToken, ['credit_request.list']);
        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => '27333444555', 'password' => self::MEMBER_NEW_PASSWORD]);
        $refreshToken = json_decode($client->getResponse()->getContent(), true)['refreshToken']['token'];

        $client->jsonRequest('PUT', self::MEMBER_URL . '/deactivate', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();

        // The still-unexpired access token stops working on the very next request.
        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($memberToken));
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', '/api/v1/refresh', ['refreshToken' => $refreshToken]);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => '27333444555', 'password' => self::MEMBER_NEW_PASSWORD]);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('PUT', self::MEMBER_URL . '/activate', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();

        $token = $this->login($client, '27333444555', self::MEMBER_NEW_PASSWORD);
        $client->jsonRequest('GET', '/api/v1/credit-request/search', [], self::bearer($token));
        self::assertResponseIsSuccessful();
    }
}
