<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\Message;

final class SimulateCreditRequestApplicationMessage
{
    public function __construct(
        private readonly string $creditRequestApplicationId,
    ) {}

    public function getCreditRequestApplicationId(): string
    {
        return $this->creditRequestApplicationId;
    }
}
