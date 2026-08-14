<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Repository\CompanyRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<CompanyUser>
 */
final class CompanyUserProvider implements UserProviderInterface
{
    public function __construct(private readonly CompanyRepositoryInterface $companyRepository) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $company = $this->companyRepository->findByCuit($identifier);

        if ($company === null) {
            throw new UserNotFoundException(sprintf('Company with CUIT "%s" not found.', $identifier));
        }

        return CompanyUser::fromCompany($company);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof CompanyUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === CompanyUser::class || is_subclass_of($class, CompanyUser::class);
    }
}
