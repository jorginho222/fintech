<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;

final class InstallmentOverdueMarker
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly InstallmentPenaltyInterestUpdater $installmentPenaltyInterestUpdater,
        private readonly InstallmentPaymentNotifierInterface $installmentPaymentNotifier,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $overdueInstallments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            status: InstallmentStatus::Pending,
            excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            dueBefore: $now,
        ));

        foreach ($overdueInstallments as $installment) {
            $installment->markOverdue();
            $this->installmentPenaltyInterestUpdater->execute($installment, $now);
            $this->creditRequestRepository->saveInstallment($installment);
            $this->installmentPaymentNotifier->notifyInstallmentOverdue($installment);
        }

        return count($overdueInstallments);
    }
}
