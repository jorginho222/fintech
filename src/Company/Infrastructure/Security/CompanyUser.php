<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security adapter over the User aggregate (admin or member): keeps framework interfaces out of the domain.
 */
final class CompanyUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE       = 'ROLE_COMPANY';
    public const string ROLE_ADMIN = 'ROLE_COMPANY_ADMIN';

    /**
     * @param string[] $permissions permission values
     */
    public function __construct(
        private readonly string $userId,
        private readonly string $companyId,
        private readonly string $taxIdentifier,
        private readonly string $hashedPassword,
        private readonly bool   $admin,
        private readonly bool   $mustChangePassword,
        private readonly array  $permissions,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->getId(),
            $user->getCompany()->getId(),
            $user->getTaxIdentifier(),
            $user->getHashedPassword(),
            $user->isAdmin(),
            $user->mustChangePassword(),
            array_map(static fn (Permission $permission): string => $permission->value, $user->permissions()),
        );
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    /**
     * The CUIT (admin) or CUIL (member) is the login identifier, so it is what the JWT carries.
     */
    public function getUserIdentifier(): string
    {
        return $this->taxIdentifier;
    }

    public function getPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getRoles(): array
    {
        return $this->admin ? [self::ROLE, self::ROLE_ADMIN] : [self::ROLE];
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function eraseCredentials(): void
    {
    }
}
