<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Exception\UserNotFoundException;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class MemberUserFinder
{
    public function __construct(
        private readonly UserRepositoryInterface                 $userRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    /**
     * Only members of the authenticated company match: another company's user, or the admin
     * itself, is reported as not found.
     */
    public function execute(string $id): MemberUser
    {
        $member = $this->userRepository->findMemberByIdAndCompany($id, $this->authenticatedCompanyIdProvider->getCompanyId());

        if ($member === null) {
            throw new UserNotFoundException();
        }

        return $member;
    }
}
