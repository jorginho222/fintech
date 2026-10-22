<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\PasswordChangeDto;
use App\Company\Domain\Exception\InvalidCurrentPasswordException;
use App\Company\Domain\Exception\NewPasswordMustDifferException;
use App\Company\Domain\Exception\PasswordChangeNotAllowedException;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\PasswordHasherInterface;

/**
 * First-login change of the admin-set password. Admins changing their own password is a later feature.
 */
final class PasswordChanger
{
    public function __construct(
        private readonly CurrentUserProvider     $currentUserProvider,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {}

    public function execute(PasswordChangeDto $input): MemberUser
    {
        $user = $this->currentUserProvider->execute();

        if (!$user instanceof MemberUser || !$user->mustChangePassword()) {
            throw new PasswordChangeNotAllowedException();
        }

        if (!$this->passwordHasher->verify($user->getHashedPassword(), $input->currentPassword)) {
            throw new InvalidCurrentPasswordException();
        }

        if ($this->passwordHasher->verify($user->getHashedPassword(), $input->newPassword)) {
            throw new NewPasswordMustDifferException();
        }

        $user->changeInitialPassword($this->passwordHasher->hash($input->newPassword));
        $this->userRepository->save($user);

        return $user;
    }
}
