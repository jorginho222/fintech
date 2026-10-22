<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Called by the JWT authenticator on every request, so permission changes and deactivation
 * apply to already-issued tokens from the next request on.
 *
 * @implements UserProviderInterface<CompanyUser>
 */
final class CompanyUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findByTaxIdentifier($identifier);

        if ($user === null || !$user->isActive()) {
            throw new UserNotFoundException(sprintf('Active user with tax identifier "%s" not found.', $identifier));
        }

        return CompanyUser::fromUser($user);
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
