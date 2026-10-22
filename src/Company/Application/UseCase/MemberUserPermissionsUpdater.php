<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\MemberUserPermissionsUpdateDto;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;

final class MemberUserPermissionsUpdater
{
    public function __construct(
        private readonly MemberUserFinder        $memberUserFinder,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $id, MemberUserPermissionsUpdateDto $input): MemberUser
    {
        $member = $this->memberUserFinder->execute($id);
        $member->replacePermissions($input->permissions());

        $this->userRepository->save($member);

        return $member;
    }
}
