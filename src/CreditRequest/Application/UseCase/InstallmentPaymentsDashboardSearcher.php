<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentsDashboardDto;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class InstallmentPaymentsDashboardSearcher
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(): InstallmentPaymentsDashboardDto
    {
        $companyId  = $this->authenticatedCompanyIdProvider->getCompanyId();
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $monthEnd   = $monthStart->modify('first day of next month');

        // Split by status, so an installment due earlier this month that is now overdue is only listed as overdue.
        $monthPayments   = $this->creditRequestRepository->findActiveCreditInstallments($companyId, InstallmentStatus::Pending, $monthStart, $monthEnd);
        $overduePayments = $this->creditRequestRepository->findActiveCreditInstallments($companyId, InstallmentStatus::Overdue);

        return new InstallmentPaymentsDashboardDto(
            monthPayments: $monthPayments,
            monthTotalAmount: $this->sumTotalAmounts($monthPayments),
            overduePayments: $overduePayments,
            overdueTotalAmount: $this->sumTotalAmounts($overduePayments),
        );
    }

    /**
     * @param Installment[] $installments
     */
    private function sumTotalAmounts(array $installments): string
    {
        return array_reduce(
            $installments,
            static fn (string $total, Installment $installment): string => bcadd($total, $installment->getTotalAmount(), 2),
            '0.00',
        );
    }
}
