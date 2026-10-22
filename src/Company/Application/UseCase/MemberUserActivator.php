<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Exception\ActiveUserLimitReachedException;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;

final class MemberUserActivator
{
    public function __construct(
        private readonly MemberUserFinder        $memberUserFinder,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $id): MemberUser
    {
        $member = $this->memberUserFinder->execute($id);

        // An already-active member doesn't take a new slot, so re-activating it is a harmless no-op.
        if ($member->isActive()) {
            return $member;
        }

        if ($this->userRepository->countActiveMembersByCompany($member->getCompany()->getId()) >= MemberUser::MAX_ACTIVE_PER_COMPANY) {
            throw new ActiveUserLimitReachedException();
        }

        $member->activate();
        $this->userRepository->save($member);

        return $member;
    }
}
