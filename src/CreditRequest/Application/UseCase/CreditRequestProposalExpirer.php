<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\CreditRequestProposalExpirationNotifierInterface;

final class CreditRequestProposalExpirer
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly CreditRequestProposalExpirationNotifierInterface $creditRequestProposalExpirationNotifier,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $expiredProposals = $this->creditRequestRepository->findExpiredProposals($now);

        foreach ($expiredProposals as $creditRequest) {
            $creditRequest->expire();
            $this->creditRequestRepository->save($creditRequest);
            $this->creditRequestProposalExpirationNotifier->notify($creditRequest);
        }

        return count($expiredProposals);
    }
}
