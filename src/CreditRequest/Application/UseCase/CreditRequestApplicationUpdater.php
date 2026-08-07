<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDecisionDto;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;

final class CreditRequestApplicationUpdater
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
    )
    {
    }

    public function execute(CreditRequestApplication $creditRequestApplication, CreditRequestApplicationResultDecisionDto $decision): CreditRequestApplication
    {
        match ($decision->status) {
            'APPROVED' => $creditRequestApplication->approve(),
            'REJECTED' => $creditRequestApplication->reject((string)$decision->rejectionReason),
        };

        $this->creditRequestApplicationRepository->save($creditRequestApplication);

        return $creditRequestApplication;
    }
}
