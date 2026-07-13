<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class InstallmentOverdueMarker
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $creditRequestsWithOverdueInstallments = $this->creditRequestRepository->findWithOverdueInstallments($now);

        $markedCount = 0;
        foreach ($creditRequestsWithOverdueInstallments as $creditRequest) {
            foreach ($creditRequest->getInstallmentCollection() as $installment) {
                if ($installment->getStatus() !== InstallmentStatus::Pending) {
                    continue;
                }

                $dueDate = $installment->getDueDate();
                if ($dueDate === null || $dueDate >= $now) {
                    continue;
                }

                $installment->markOverdue();
                $markedCount++;
            }

            $this->creditRequestRepository->save($creditRequest);
        }

        return $markedCount;
    }
}
