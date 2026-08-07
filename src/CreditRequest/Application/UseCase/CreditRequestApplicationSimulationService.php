<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Service\CreditRequestApplicationWebhookClientInterface;

final class CreditRequestApplicationSimulationService
{
    public function __construct(
        private readonly CreditRequestApplicationWebhookClientInterface $creditRequestApplicationWebhookClient,
    ) {
    }

    public function execute(CreditRequestApplication $creditRequestApplication): void
    {
        $this->creditRequestApplicationWebhookClient->sendRequest(
            $creditRequestApplication->getId(),
            $creditRequestApplication->getCompany()->getCuit(),
            $creditRequestApplication->getAmount(),
        );
    }
}
