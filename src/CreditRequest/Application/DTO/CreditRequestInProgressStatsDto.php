<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

final class CreditRequestInProgressStatsDto
{
    public function __construct(
        public readonly int    $total,
        public readonly ?float $paidPercentage,
    ) {}
}
