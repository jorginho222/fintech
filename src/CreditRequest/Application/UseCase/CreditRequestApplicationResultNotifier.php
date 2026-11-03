<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\Domain\Service\CreditRequestApplicationNotifierInterface;

final class CreditRequestApplicationResultNotifier
{
    public function __construct(
        private readonly CreditRequestApplicationNotifierInterface $creditRequestApplicationNotifier,
    ) {}

    public function execute(CreditRequestApplication $creditRequestApplication): void
    {
        match ($creditRequestApplication->getStatus()) {
            CreditRequestApplicationStatus::Approved => $this->creditRequestApplicationNotifier->notifyApproved($creditRequestApplication),
            CreditRequestApplicationStatus::Rejected => $this->creditRequestApplicationNotifier->notifyRejected($creditRequestApplication),
            CreditRequestApplicationStatus::EvaluationPending => null,
        };
    }
}
