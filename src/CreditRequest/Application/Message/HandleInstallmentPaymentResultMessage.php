<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\Message;

final class HandleInstallmentPaymentResultMessage
{
    public function __construct(
        private readonly string $payload,
    ) {}

    public function getPayload(): string
    {
        return $this->payload;
    }
}
