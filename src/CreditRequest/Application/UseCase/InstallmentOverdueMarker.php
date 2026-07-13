<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class InstallmentOverdueMarker
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $overdueInstallments = $this->creditRequestRepository->findWithOverdueInstallments($now);

        foreach ($overdueInstallments as $installment) {
            $installment->markOverdue();
            $this->creditRequestRepository->save($installment->getCreditRequest());
        }

        return count($overdueInstallments);
    }
}
