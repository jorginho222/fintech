<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait ApiAuthenticationTrait
{
    private const string REGISTER_URL        = '/api/v1/company_registration';
    private const string LOGIN_URL           = '/api/v1/login';
    private const string MEMBER_NEW_PASSWORD = 'Changed456?';

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed> the payload the company was registered with
     */
    private function registerCompany(KernelBrowser $client, array $overrides = []): array
    {
        $payload = array_merge([
            'id'           => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'socialReason' => 'Empresa SRL',
            'cuit'         => '20123456780',
            'email'        => 'empresa@empresa.com',
            'taxStatus'    => 'monotributo',
            'password'     => 'Secret123!',
        ], $overrides);

        $client->jsonRequest('POST', self::REGISTER_URL, $payload);

        return $payload;
    }

    private function login(KernelBrowser $client, string $taxIdentifier, string $password): string
    {
        return $this->loginTokens($client, $taxIdentifier, $password)[0];
    }

    /**
     * @return array{0: string, 1: string} the access token and refresh token
     */
    private function loginTokens(KernelBrowser $client, string $taxIdentifier, string $password): array
    {
        $client->jsonRequest('POST', self::LOGIN_URL, ['taxIdentifier' => $taxIdentifier, 'password' => $password]);
        $body = json_decode($client->getResponse()->getContent(), true);

        return [$body['accessToken']['token'], $body['refreshToken']['token']];
    }

    /**
     * Registers a company and returns its payload plus a usable bearer token.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function registerAndLogin(KernelBrowser $client, array $overrides = []): array
    {
        $company = $this->registerCompany($client, $overrides);

        return [$company, $this->login($client, $company['cuit'], $company['password'])];
    }

    /**
     * Registers a company and returns its payload plus a usable access and refresh token.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function registerAndLoginWithTokens(KernelBrowser $client, array $overrides = []): array
    {
        $company = $this->registerCompany($client, $overrides);
        [$accessToken, $refreshToken] = $this->loginTokens($client, $company['cuit'], $company['password']);

        return [$company, $accessToken, $refreshToken];
    }

    /**
     * Creates a member through the admin API. The member still has to change its password.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed> the payload the member was created with
     */
    private function createMember(KernelBrowser $client, string $adminToken, array $overrides = []): array
    {
        $payload = array_merge([
            'id'            => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'taxIdentifier' => '27333444555',
            'displayName'   => 'Ana Pérez',
            'password'      => 'Initial123!',
            'permissions'   => [],
        ], $overrides);

        $client->jsonRequest('POST', '/api/v1/user', $payload, self::bearer($adminToken));

        return $payload;
    }

    /**
     * Creates a member, completes its first-login password change and returns a bearer token
     * with normal (permission-based) access.
     *
     * @param string[]             $permissions
     * @param array<string, mixed> $overrides
     */
    private function createMemberAndLogin(KernelBrowser $client, string $adminToken, array $permissions, array $overrides = []): string
    {
        $member = $this->createMember($client, $adminToken, [...$overrides, 'permissions' => $permissions]);

        $initialToken = $this->login($client, $member['taxIdentifier'], $member['password']);
        $client->jsonRequest(
            'PUT',
            '/api/v1/me/password',
            ['currentPassword' => $member['password'], 'newPassword' => self::MEMBER_NEW_PASSWORD],
            self::bearer($initialToken),
        );

        return $this->login($client, $member['taxIdentifier'], self::MEMBER_NEW_PASSWORD);
    }

    /**
     * @return array<string, string>
     */
    private static function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    }
}
