<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

/**
 * The company's own account: logs in with the company CUIT and holds every permission.
 */
class AdminUser extends User
{
    public static function forCompany(string $id, Company $company, string $hashedPassword): self
    {
        return new self($id, $company, $company->getCuit(), $hashedPassword);
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function hasPermission(Permission $permission): bool
    {
        return true;
    }

    public function permissions(): array
    {
        return Permission::cases();
    }

    public function mustChangePassword(): bool
    {
        return false;
    }
}
