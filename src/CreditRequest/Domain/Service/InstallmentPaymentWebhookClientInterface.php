<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

interface InstallmentPaymentWebhookClientInterface
{
    public function sendRequest(string $installmentId, string $cuit, string $amount): void;
}
