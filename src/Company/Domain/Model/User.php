<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

/**
 * A login identity acting on behalf of a company: either its admin or one of its members.
 */
abstract class User
{
    protected bool $active;

    public function __construct(
        private string $id,
        private Company $company,
        private string $taxIdentifier,
        private string $hashedPassword,
    ) {
        if (!preg_match('/^\d{11}$/', $taxIdentifier)) {
            throw new \InvalidArgumentException('Tax identifier must be exactly 11 digits.');
        }
        $this->active = true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getTaxIdentifier(): string
    {
        return $this->taxIdentifier;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    protected function changeHashedPassword(string $hashedPassword): void
    {
        $this->hashedPassword = $hashedPassword;
    }

    abstract public function isAdmin(): bool;

    abstract public function hasPermission(Permission $permission): bool;

    /** @return Permission[] */
    abstract public function permissions(): array;

    abstract public function mustChangePassword(): bool;
}
