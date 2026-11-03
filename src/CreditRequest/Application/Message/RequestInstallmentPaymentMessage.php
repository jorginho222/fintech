<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\Message;

final class RequestInstallmentPaymentMessage
{
    public function __construct(
        private readonly string $installmentId,
    ) {}

    public function getInstallmentId(): string
    {
        return $this->installmentId;
    }
}
