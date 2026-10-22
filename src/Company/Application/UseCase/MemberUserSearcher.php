<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class MemberUserSearcher
{
    public function __construct(
        private readonly UserRepositoryInterface                 $userRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    /** @return MemberUser[] */
    public function execute(): array
    {
        return $this->userRepository->findMembersByCompany($this->authenticatedCompanyIdProvider->getCompanyId());
    }
}
