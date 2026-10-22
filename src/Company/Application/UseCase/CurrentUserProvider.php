<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Exception\UserNotFoundException;
use App\Company\Domain\Model\User;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthenticatedUserIdProviderInterface;

final class CurrentUserProvider
{
    public function __construct(
        private readonly UserRepositoryInterface              $userRepository,
        private readonly AuthenticatedUserIdProviderInterface $authenticatedUserIdProvider,
    ) {}

    public function execute(): User
    {
        $user = $this->userRepository->findById($this->authenticatedUserIdProvider->getUserId());

        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }
}
