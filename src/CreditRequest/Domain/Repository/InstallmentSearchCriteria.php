<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;

/**
 * Every criterion is optional; the ones given are combined with AND.
 * $dueFrom is inclusive and $dueBefore exclusive. Installments without an attempt are never
 * filtered out by $excludedPaymentAttemptStatus.
 */
final class InstallmentSearchCriteria
{
    public function __construct(
        public readonly ?string                          $companyId = null,
        public readonly ?CreditRequestStatus             $creditRequestStatus = null,
        public readonly ?InstallmentStatus               $status = null,
        public readonly ?InstallmentPaymentAttemptStatus $paymentAttemptStatus = null,
        public readonly ?InstallmentPaymentAttemptStatus $excludedPaymentAttemptStatus = null,
        public readonly ?\DateTimeImmutable              $dueFrom = null,
        public readonly ?\DateTimeImmutable              $dueBefore = null,
    ) {}
}
