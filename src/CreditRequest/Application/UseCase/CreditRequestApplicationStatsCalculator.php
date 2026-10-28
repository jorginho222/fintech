<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\CreditRequestApplicationStatsDto;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class CreditRequestApplicationStatsCalculator
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
        private readonly AuthenticatedCompanyIdProviderInterface     $authenticatedCompanyIdProvider,
    ) {}

    public function execute(): CreditRequestApplicationStatsDto
    {
        $counts = $this->creditRequestApplicationRepository->countByStatus(
            $this->authenticatedCompanyIdProvider->getCompanyId(),
        );

        return new CreditRequestApplicationStatsDto(
            // Sums every status, not just the three below, so the total never drops applications.
            total: array_sum($counts),
            approved: $counts[CreditRequestApplicationStatus::Approved->value] ?? 0,
            rejected: $counts[CreditRequestApplicationStatus::Rejected->value] ?? 0,
            evaluationPending: $counts[CreditRequestApplicationStatus::EvaluationPending->value] ?? 0,
        );
    }
}
