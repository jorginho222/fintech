<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Exception\CreditRequestNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class CreditRequestFinder
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(string $id): CreditRequest
    {
        $creditRequest = $this->creditRequestRepository->findById($id);

        if (
            $creditRequest === null
            || $creditRequest->getCompany()->getId() !== $this->authenticatedCompanyIdProvider->getCompanyId()
        ) {
            throw new CreditRequestNotFoundException();
        }

        return $creditRequest;
    }
}
