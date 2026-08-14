<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class CreditRequestApplicationSearcher
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
        private readonly AuthenticatedCompanyIdProviderInterface     $authenticatedCompanyIdProvider,
    ) {}

    public function execute(): array
    {
        return $this->creditRequestApplicationRepository->search(
            $this->authenticatedCompanyIdProvider->getCompanyId(),
        );
    }
}
