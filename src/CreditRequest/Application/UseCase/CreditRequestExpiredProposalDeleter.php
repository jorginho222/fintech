<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class CreditRequestExpiredProposalDeleter
{
    private const int RETENTION_DAYS = 15;

    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $before = $now->modify(sprintf('-%d days', self::RETENTION_DAYS));

        $expiredProposals = $this->creditRequestRepository->findExpiredProposalsForDeletion($before);

        foreach ($expiredProposals as $creditRequest) {
            $this->creditRequestRepository->delete($creditRequest);
        }

        return count($expiredProposals);
    }
}
