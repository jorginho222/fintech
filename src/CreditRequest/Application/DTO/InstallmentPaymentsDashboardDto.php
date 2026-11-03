<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use App\CreditRequest\Domain\Model\Installment;

final class InstallmentPaymentsDashboardDto
{
    /**
     * @param Installment[] $monthPayments
     * @param Installment[] $overduePayments
     * @param Installment[] $processingPayments
     */
    public function __construct(
        public readonly array  $monthPayments,
        public readonly string $monthTotalAmount,
        public readonly array  $overduePayments,
        public readonly string $overdueTotalAmount,
        public readonly array  $processingPayments,
        public readonly string $processingTotalAmount,
    ) {}
}
