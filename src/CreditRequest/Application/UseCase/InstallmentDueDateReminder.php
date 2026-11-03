<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;

final class InstallmentDueDateReminder
{
    private const int REMINDER_DAYS_BEFORE_DUE = 3;

    public function __construct(
        private readonly CreditRequestRepositoryInterface    $creditRequestRepository,
        private readonly InstallmentPaymentNotifierInterface $installmentPaymentNotifier,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $dueDay = $now->setTime(0, 0)->modify(sprintf('+%d days', self::REMINDER_DAYS_BEFORE_DUE));

        $installments = $this->creditRequestRepository->searchInstallments(new InstallmentSearchCriteria(
            creditRequestStatus: CreditRequestStatus::Active,
            status: InstallmentStatus::Pending,
            excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            dueFrom: $dueDay,
            dueBefore: $dueDay->modify('+1 day'),
        ));

        foreach ($installments as $installment) {
            $this->installmentPaymentNotifier->notifyDueDateReminder($installment);
        }

        return count($installments);
    }
}
