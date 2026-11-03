<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;

interface InstallmentPaymentNotifierInterface
{
    public function notifyPaymentProcessing(Installment $installment): void;

    public function notifyPaymentApproved(Installment $installment): void;

    public function notifyPaymentRejected(Installment $installment): void;

    public function notifyPaymentProcessingFailed(Installment $installment): void;

    public function notifyPaymentProcessingTimeout(Installment $installment): void;

    public function notifyInstallmentOverdue(Installment $installment): void;

    public function notifyDueDateReminder(Installment $installment): void;

    public function notifyCreditRequestPaid(CreditRequest $creditRequest): void;
}
