<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class CreditRequestProposalExpirer
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $expiredProposals = $this->creditRequestRepository->findExpiredProposals($now);

        foreach ($expiredProposals as $creditRequest) {
            $creditRequest->expire();
            $this->creditRequestRepository->save($creditRequest);
        }

        return count($expiredProposals);
    }
}
