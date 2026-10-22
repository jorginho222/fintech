<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;

final class MemberUserDeactivator
{
    public function __construct(
        private readonly MemberUserFinder        $memberUserFinder,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $id): MemberUser
    {
        $member = $this->memberUserFinder->execute($id);
        $member->deactivate();

        $this->userRepository->save($member);

        return $member;
    }
}
