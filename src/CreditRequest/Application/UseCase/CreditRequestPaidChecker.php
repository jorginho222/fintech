<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class CreditRequestPaidChecker
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
    ) {}

    public function execute(CreditRequest $creditRequest): void
    {
        /** @var Installment $installment */
        foreach ($creditRequest->getInstallmentCollection() as $installment) {
            if ($installment->getStatus() !== InstallmentStatus::Paid) {
                return;
            }
        }

        $creditRequest->changeStatus(CreditRequestStatus::Paid);
        $this->creditRequestRepository->save($creditRequest);
    }
}
