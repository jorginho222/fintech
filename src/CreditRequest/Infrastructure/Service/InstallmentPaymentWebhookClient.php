<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Service\InstallmentPaymentWebhookClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InstallmentPaymentWebhookClient implements InstallmentPaymentWebhookClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $webhookUrl,
    ) {
    }

    public function sendRequest(string $installmentId, string $cuit, string $amount): void
    {
        $this->httpClient->request('POST', $this->webhookUrl, [
            'headers' => [
                'Idempotency-Key' => $installmentId,
            ],
            'json' => [
                'installmentId' => $installmentId,
                'cuit' => $cuit,
                'amount' => $amount,
            ],
        ]);
    }
}
