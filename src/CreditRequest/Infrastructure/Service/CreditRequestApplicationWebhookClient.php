<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Service\CreditRequestApplicationWebhookClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CreditRequestApplicationWebhookClient implements CreditRequestApplicationWebhookClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $webhookUrl,
    ) {
    }

    public function sendRequest(string $applicationId, string $cuit, string $requestedAmount): void
    {
        $this->httpClient->request('POST', $this->webhookUrl, [
            'json' => [
                'applicationId' => $applicationId,
                'cuit' => $cuit,
                'requestedAmount' => $requestedAmount,
            ],
        ]);
    }
}
