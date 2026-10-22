<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use App\Company\Domain\Model\Permission;
use App\Tests\Functional\ApiAuthenticationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberUserManagementTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string USER_URL = '/api/v1/user';

    public function testTheAdminListsFindsRenamesAndRegrantsItsMembers(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken, ['permissions' => ['total_debt.view']]);

        $client->jsonRequest('GET', self::USER_URL . '/search', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $list, 'The admin itself is not listed as a member.');
        self::assertSame($member['id'], $list[0]['id']);

        $client->jsonRequest('GET', self::USER_URL . '/' . $member['id'], [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
        self::assertSame('Ana Pérez', $this->body($client)['displayName']);

        $client->jsonRequest('PUT', self::USER_URL . '/' . $member['id'], ['displayName' => 'Ana María Pérez'], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
        self::assertSame('Ana María Pérez', $this->body($client)['displayName']);

        $client->jsonRequest(
            'PUT',
            self::USER_URL . '/' . $member['id'] . '/permissions',
            ['permissions' => ['installment.pay', 'credit_request.list']],
            self::bearer($adminToken),
        );
        self::assertResponseIsSuccessful();
        self::assertSame(['installment.pay', 'credit_request.list'], $this->body($client)['permissions']);
    }

    public function testAPermissionsUpdateWithoutThePermissionsKeyIsRejected(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken, ['permissions' => ['total_debt.view']]);

        $client->jsonRequest('PUT', self::USER_URL . '/' . $member['id'] . '/permissions', [], self::bearer($adminToken));

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheAdminDeactivatesAndActivatesAMember(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken);

        $client->jsonRequest('PUT', self::USER_URL . '/' . $member['id'] . '/deactivate', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
        self::assertFalse($this->body($client)['active']);

        $client->jsonRequest('PUT', self::USER_URL . '/' . $member['id'] . '/activate', [], self::bearer($adminToken));
        self::assertResponseIsSuccessful();
        self::assertTrue($this->body($client)['active']);
    }

    public function testTheCatalogueIsGroupedByResource(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $client->jsonRequest('GET', self::USER_URL . '/permission-catalogue', [], self::bearer($adminToken));

        self::assertResponseIsSuccessful();
        self::assertSame([
            ['resource' => 'credit_request_application', 'permissions' => ['credit_request_application.list', 'credit_request_application.create', 'credit_request.confirm']],
            ['resource' => 'credit_requests', 'permissions' => ['credit_request.view', 'credit_request.list', 'installment.pay']],
            ['resource' => 'payment_calendar', 'permissions' => ['payment_calendar.view']],
            ['resource' => 'total_debt', 'permissions' => ['total_debt.view']],
        ], $this->body($client));
    }

    public function testAnotherCompanysMemberIsNotFound(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $member = $this->createMember($client, $adminToken);

        [, $otherAdminToken] = $this->registerAndLogin($client, [
            'id'    => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'cuit'  => '30987654321',
            'email' => 'otra@empresa.com',
        ]);

        foreach ([
            ['GET', ''],
            ['PUT', ''],
            ['PUT', '/permissions'],
            ['PUT', '/deactivate'],
            ['PUT', '/activate'],
        ] as [$method, $suffix]) {
            $client->jsonRequest($method, self::USER_URL . '/' . $member['id'] . $suffix, ['displayName' => 'X', 'permissions' => []], self::bearer($otherAdminToken));
            self::assertResponseStatusCodeSame(404, $method . ' ' . $suffix);
        }

        $client->jsonRequest('GET', self::USER_URL . '/search', [], self::bearer($otherAdminToken));
        self::assertSame([], $this->body($client));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminOnlyRoutes(): iterable
    {
        yield 'create' => ['POST', ''];
        yield 'search' => ['GET', '/search'];
        yield 'catalogue' => ['GET', '/permission-catalogue'];
        yield 'find' => ['GET', '/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb'];
        yield 'update' => ['PUT', '/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb'];
        yield 'permissions' => ['PUT', '/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb/permissions'];
        yield 'deactivate' => ['PUT', '/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb/deactivate'];
        yield 'activate' => ['PUT', '/bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb/activate'];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function testAMemberCannotReachUserManagementWhateverItsPermissions(string $method, string $suffix): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);
        $memberToken = $this->createMemberAndLogin($client, $adminToken, Permission::values());

        $client->jsonRequest($method, self::USER_URL . $suffix, [], self::bearer($memberToken));

        self::assertResponseStatusCodeSame(403);
    }

    private function body(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent(), true);
    }
}
