<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Company\Domain\Model\MemberUser;
use App\Tests\Functional\ApiAuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberUserCreatePostControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    public function testTheAdminCreatesAMemberThatCanLogInWithItsCuil(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $member = $this->createMember($client, $adminToken, ['permissions' => ['total_debt.view']]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([
            'id'                 => $member['id'],
            'type'               => 'member',
            'taxIdentifier'      => $member['taxIdentifier'],
            'displayName'        => $member['displayName'],
            'active'             => true,
            'mustChangePassword' => true,
            'permissions'        => ['total_debt.view'],
        ], $body);

        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => $member['taxIdentifier'], 'password' => $member['password']]);

        self::assertResponseIsSuccessful();
        $login = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('member', $login['user']['type']);
        self::assertTrue($login['user']['mustChangePassword']);
    }

    public function testACuilAlreadyUsedAsACompanyCuitIsRejected(): void
    {
        $client = static::createClient();
        [$company, $adminToken] = $this->registerAndLogin($client);

        $this->createMember($client, $adminToken, ['taxIdentifier' => $company['cuit']]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testAWeakPasswordIsRejectedListingTheUnmetRules(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $this->createMember($client, $adminToken, ['password' => 'weakpass']);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(3, json_decode($client->getResponse()->getContent(), true)['errors']['password']);
    }

    public function testAnUnknownPermissionIsRejected(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $this->createMember($client, $adminToken, ['permissions' => ['total_debt.view', 'company.delete']]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheEleventhActiveMemberIsRejectedUntilASlotIsFreed(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        for ($i = 0; $i < MemberUser::MAX_ACTIVE_PER_COMPANY; $i++) {
            $this->createMember($client, $adminToken, self::nthMember($i));
            self::assertResponseStatusCodeSame(201);
        }

        $this->createMember($client, $adminToken, self::nthMember(10));
        self::assertResponseStatusCodeSame(409);

        $client->jsonRequest('PUT', '/api/v1/user/' . self::nthMember(0)['id'] . '/deactivate', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();

        $this->createMember($client, $adminToken, self::nthMember(10));
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @return array{id: string, taxIdentifier: string}
     */
    private static function nthMember(int $n): array
    {
        return [
            'id'            => sprintf('bbbbbbbb-bbbb-4bbb-bbbb-%012d', $n),
            'taxIdentifier' => sprintf('27%09d', $n),
        ];
    }
}
