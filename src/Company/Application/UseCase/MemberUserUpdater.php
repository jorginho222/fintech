<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\MemberUserUpdateDto;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;

final class MemberUserUpdater
{
    public function __construct(
        private readonly MemberUserFinder        $memberUserFinder,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $id, MemberUserUpdateDto $input): MemberUser
    {
        $member = $this->memberUserFinder->execute($id);
        $member->rename(trim($input->displayName));

        $this->userRepository->save($member);

        return $member;
    }
}
