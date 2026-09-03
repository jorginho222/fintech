<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Exception\CompanyNotFoundException;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Application\DTO\CreditRequestApplicationCreateDto;
use App\CreditRequest\Application\Message\SimulateCreditRequestApplicationMessage;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreditRequestApplicationHandler
{
    public function __construct(
        private readonly CompanyRepositoryInterface              $companyRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
        private readonly CreditRequestApplicationCreator          $creditRequestApplicationCreator,
        private readonly MessageBusInterface                      $messageBus,
    ) {}

    public function execute(CreditRequestApplicationCreateDto $dto): CreditRequestApplication
    {
        $company = $this->companyRepository->findById(
            $this->authenticatedCompanyIdProvider->getCompanyId(),
        );
        if ($company === null) {
            throw new CompanyNotFoundException();
        }

        $creditRequestApplication = $this->creditRequestApplicationCreator->execute($company, $dto->amount, $dto->installmentQuantity);

        $this->messageBus->dispatch(new SimulateCreditRequestApplicationMessage($creditRequestApplication->getId()));

        return $creditRequestApplication;
    }
}
