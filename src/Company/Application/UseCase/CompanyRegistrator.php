<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyRegistrationDto;
use App\Company\Domain\Exception\CompanyCuitAlreadyRegisteredException;
use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CompanyRegistrator
{
    public function __construct(
        private readonly CompanyRepositoryInterface  $companyRepository,
        private readonly UserRepositoryInterface     $userRepository,
        private readonly PasswordHasherInterface     $passwordHasher,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    public function execute(CompanyRegistrationDto $input): void
    {
        // The CUIT becomes the admin's login, so it must not clash with a member's CUIL either.
        if ($this->companyRepository->findByCuit($input->cuit) !== null
            || $this->userRepository->findByTaxIdentifier($input->cuit) !== null) {
            throw new CompanyCuitAlreadyRegisteredException();
        }

        $company = new Company(
            $input->id,
            $input->socialReason,
            $input->cuit,
            $input->email,
            TaxStatus::from($input->taxStatus),
        );
        $admin = AdminUser::forCompany(Uuid::v4()->toRfc4122(), $company, $this->passwordHasher->hash($input->password));

        $this->transactionManager->transactional(function () use ($company, $admin): void {
            $this->companyRepository->save($company);
            $this->userRepository->save($admin);
        });
    }
}
