<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Service\AuthenticatedUserIdProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Reads the id from the user the provider loaded for this request, rather than from a token
 * claim, so it also works for access tokens issued before the userId claim existed.
 */
final class SecurityAuthenticatedUserIdProvider implements AuthenticatedUserIdProviderInterface
{
    public function __construct(private readonly Security $security) {}

    public function getUserId(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof CompanyUser) {
            throw new AuthenticationCredentialsNotFoundException('An authenticated user is required.');
        }

        return $user->getUserId();
    }
}
