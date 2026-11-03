<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;

final class InstallmentPenaltyInterestAccruer
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface  $creditRequestRepository,
        private readonly InstallmentPenaltyInterestUpdater $installmentPenaltyInterestUpdater,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        // A processing payment is skipped because its amount was already sent to the payment service.
        // Its last calculation date doesn't move, so the paused days are charged if the payment isn't approved.
        $overdueInstallments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            creditRequestStatus: CreditRequestStatus::Active,
            status: InstallmentStatus::Overdue,
            excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
        ));

        $accruedCount = 0;
        foreach ($overdueInstallments as $installment) {
            if (!$this->installmentPenaltyInterestUpdater->execute($installment, $now)) {
                continue;
            }

            $this->creditRequestRepository->saveInstallment($installment);
            $accruedCount++;
        }

        return $accruedCount;
    }
}
