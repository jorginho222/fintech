<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

use App\Company\Domain\Exception\PasswordChangeNotAllowedException;

/**
 * A person created by the company admin, logging in with their CUIL and limited to the
 * permissions granted to them. Never deleted: only deactivated and activated again.
 */
class MemberUser extends User
{
    public const int MAX_ACTIVE_PER_COMPANY = 10;

    /** @var string[] permission values, kept as strings so the mapping stays a plain JSON column */
    private array $permissions;
    private bool $mustChangePassword;

    /**
     * @param Permission[] $permissions
     */
    public function __construct(
        string $id,
        Company $company,
        string $taxIdentifier,
        string $hashedPassword,
        private string $displayName,
        array $permissions,
    ) {
        parent::__construct($id, $company, $taxIdentifier, $hashedPassword);
        $this->assertDisplayName($displayName);
        $this->permissions        = self::toValues($permissions);
        $this->mustChangePassword = true;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function rename(string $displayName): void
    {
        $this->assertDisplayName($displayName);
        $this->displayName = $displayName;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    public function permissions(): array
    {
        return array_map(static fn (string $value): Permission => Permission::from($value), $this->permissions);
    }

    /**
     * @param Permission[] $permissions
     */
    public function replacePermissions(array $permissions): void
    {
        $this->permissions = self::toValues($permissions);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    /**
     * Replaces the admin-set password on first login. Only allowed once.
     */
    public function changeInitialPassword(string $hashedPassword): void
    {
        if (!$this->mustChangePassword) {
            throw new PasswordChangeNotAllowedException();
        }

        $this->changeHashedPassword($hashedPassword);
        $this->mustChangePassword = false;
    }

    private function assertDisplayName(string $displayName): void
    {
        if (trim($displayName) === '') {
            throw new \InvalidArgumentException('Display name cannot be blank.');
        }
    }

    /**
     * @param Permission[] $permissions
     *
     * @return string[]
     */
    private static function toValues(array $permissions): array
    {
        return array_values(array_unique(array_map(
            static fn (Permission $permission): string => $permission->value,
            $permissions,
        )));
    }
}
