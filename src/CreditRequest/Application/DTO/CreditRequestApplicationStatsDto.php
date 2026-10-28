<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

final class CreditRequestApplicationStatsDto
{
    public function __construct(
        public readonly int $total,
        public readonly int $approved,
        public readonly int $rejected,
        public readonly int $evaluationPending,
    ) {}
}
