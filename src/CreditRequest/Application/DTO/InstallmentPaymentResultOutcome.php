<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;

final class InstallmentPaymentResultOutcome
{
    /**
     * @param InstallmentPaymentAttemptStatus|null $appliedAttemptStatus null when the result was ignored
     */
    public function __construct(
        public readonly Installment                      $installment,
        public readonly ?InstallmentPaymentAttemptStatus $appliedAttemptStatus,
        public readonly bool                             $creditRequestPaid,
    ) {}
}
