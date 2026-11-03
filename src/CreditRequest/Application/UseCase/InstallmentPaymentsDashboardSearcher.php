<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentsDashboardDto;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
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
        $monthPayments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            companyId: $companyId,
            creditRequestStatus: CreditRequestStatus::Active,
            status: InstallmentStatus::Pending,
            excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            dueFrom: $monthStart,
            dueBefore: $monthEnd,
        ));
        $overduePayments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            companyId: $companyId,
            creditRequestStatus: CreditRequestStatus::Active,
            status: InstallmentStatus::Overdue,
            excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
        ));
        $processingPayments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            companyId: $companyId,
            creditRequestStatus: CreditRequestStatus::Active,
            paymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
        ));

        return new InstallmentPaymentsDashboardDto(
            monthPayments: $monthPayments,
            monthTotalAmount: $this->sumTotalAmounts($monthPayments),
            overduePayments: $overduePayments,
            overdueTotalAmount: $this->sumTotalAmounts($overduePayments),
            processingPayments: $processingPayments,
            processingTotalAmount: $this->sumTotalAmounts($processingPayments),
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
