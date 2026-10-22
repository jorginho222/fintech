<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\MemberUserCreateDto;
use App\Company\Domain\Exception\ActiveUserLimitReachedException;
use App\Company\Domain\Exception\CompanyNotFoundException;
use App\Company\Domain\Exception\UserTaxIdentifierAlreadyTakenException;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class MemberUserCreator
{
    public function __construct(
        private readonly UserRepositoryInterface                 $userRepository,
        private readonly CompanyRepositoryInterface              $companyRepository,
        private readonly PasswordHasherInterface                 $passwordHasher,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(MemberUserCreateDto $input): MemberUser
    {
        $company = $this->companyRepository->findById($this->authenticatedCompanyIdProvider->getCompanyId());
        if ($company === null) {
            throw new CompanyNotFoundException();
        }

        // Admin CUITs and member CUILs share one identifier space (a sole proprietor's CUIT is their CUIL).
        if ($this->userRepository->findByTaxIdentifier($input->taxIdentifier) !== null) {
            throw new UserTaxIdentifierAlreadyTakenException();
        }

        if ($this->userRepository->countActiveMembersByCompany($company->getId()) >= MemberUser::MAX_ACTIVE_PER_COMPANY) {
            throw new ActiveUserLimitReachedException();
        }

        $member = new MemberUser(
            $input->id,
            $company,
            $input->taxIdentifier,
            $this->passwordHasher->hash($input->password),
            trim($input->displayName),
            $input->permissions(),
        );

        $this->userRepository->save($member);

        return $member;
    }
}
