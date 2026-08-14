<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Service\CreditRequestApplicationWebhookClientInterface;
use App\CreditRequest\Infrastructure\Security\CreditBureauWebhookAuthenticator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CreditRequestApplicationWebhookClient implements CreditRequestApplicationWebhookClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $webhookUrl,
        private readonly string $webhookSecret,
    ) {
    }

    public function sendRequest(string $applicationId, string $cuit, string $requestedAmount): void
    {
        $this->httpClient->request('POST', $this->webhookUrl, [
            // The bureau replays this secret on its result callback so we can authenticate it.
            'headers' => [
                CreditBureauWebhookAuthenticator::HEADER => $this->webhookSecret,
            ],
            'json' => [
                'applicationId' => $applicationId,
                'cuit' => $cuit,
                'requestedAmount' => $requestedAmount,
            ],
        ]);
    }
}
