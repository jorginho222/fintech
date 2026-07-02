<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

final class InstallmentCreateDto
{
    public function __construct(
        public readonly int $periodNumber,
        public readonly string $capitalAmount,
        public readonly string $interestAmount,
        public readonly string $taxOnInterestAmount,
        public readonly string $totalAmount,
        public readonly \DateTimeImmutable $dueDate,
    ) {}
}
