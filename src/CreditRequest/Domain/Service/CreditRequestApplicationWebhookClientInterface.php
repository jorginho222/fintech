<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

interface CreditRequestApplicationWebhookClientInterface
{
    public function sendRequest(string $applicationId, string $cuit, string $requestedAmount): void;
}
