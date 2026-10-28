<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\CreditRequestInProgressStatsDto;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class CreditRequestInProgressStatsCalculator
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(): CreditRequestInProgressStatsDto
    {
        $companyId = $this->authenticatedCompanyIdProvider->getCompanyId();

        $installmentCounts = $this->creditRequestRepository->countInstallmentsByStatus($companyId, CreditRequestStatus::Active);
        $installmentTotal  = array_sum($installmentCounts);
        $paidInstallments  = $installmentCounts[InstallmentStatus::Paid->value] ?? 0;

        return new CreditRequestInProgressStatsDto(
            total: $this->creditRequestRepository->countByCompanyAndStatus($companyId, CreditRequestStatus::Active),
            // Pools the installments of every active credit; null when there is nothing to measure.
            paidPercentage: $installmentTotal === 0 ? null : round($paidInstallments * 100 / $installmentTotal, 2),
        );
    }
}
