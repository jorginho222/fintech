<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Model\Company;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security adapter over the Company aggregate: keeps framework interfaces out of the domain.
 */
final class CompanyUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE = 'ROLE_COMPANY';

    public function __construct(
        private readonly string $companyId,
        private readonly string $cuit,
        private readonly string $hashedPassword,
    ) {
    }

    public static function fromCompany(Company $company): self
    {
        return new self($company->getId(), $company->getCuit(), $company->getHashedPassword());
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    /**
     * The CUIT is the login identifier, so it is what the JWT carries.
     */
    public function getUserIdentifier(): string
    {
        return $this->cuit;
    }

    public function getPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function eraseCredentials(): void
    {
    }
}
