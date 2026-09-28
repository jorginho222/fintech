<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\TotalDebtSearchDto;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class TotalDebtCalculator
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(TotalDebtSearchDto $dto): string
    {
        $periodStart = new \DateTimeImmutable('today');
        $periodEnd   = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $dto->year, $dto->month)))
            ->modify('first day of next month');

        $installments = $this->creditRequestRepository->findPendingInstallmentsToPay(
            $this->authenticatedCompanyIdProvider->getCompanyId(),
            $periodStart,
            $periodEnd,
            includeOverdueBeforePeriodStart: true,
        );

        return array_reduce(
            $installments,
            static fn (string $total, Installment $installment): string => bcadd($total, $installment->getTotalAmount(), 2),
            '0.00',
        );
    }
}
