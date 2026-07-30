<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequestApplication;

final class CreditRequestApplicationSimulationService
{
    public function execute(CreditRequestApplication $creditRequestApplication): void
    {
        // TODO: call the webhook service to simulate the credit bureau response
        // (insufficient score, exceeded amount, etc.) and update the
        // CreditRequestApplication status accordingly.
    }
}
