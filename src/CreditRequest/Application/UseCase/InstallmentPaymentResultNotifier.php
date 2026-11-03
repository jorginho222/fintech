<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultOutcome;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;

final class InstallmentPaymentResultNotifier
{
    public function __construct(
        private readonly InstallmentPaymentNotifierInterface $installmentPaymentNotifier,
    ) {}

    public function execute(InstallmentPaymentResultOutcome $outcome): void
    {
        $installment = $outcome->installment;

        match ($outcome->appliedAttemptStatus) {
            InstallmentPaymentAttemptStatus::Approved => $this->installmentPaymentNotifier->notifyPaymentApproved($installment),
            InstallmentPaymentAttemptStatus::Rejected => $this->installmentPaymentNotifier->notifyPaymentRejected($installment),
            InstallmentPaymentAttemptStatus::ProcessingFailed => $this->installmentPaymentNotifier->notifyPaymentProcessingFailed($installment),
            default => null,
        };

        if ($outcome->creditRequestPaid) {
            $this->installmentPaymentNotifier->notifyCreditRequestPaid($installment->getCreditRequest());
        }
    }
}
