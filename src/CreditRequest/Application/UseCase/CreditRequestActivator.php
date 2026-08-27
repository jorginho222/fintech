<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Exception\CreditRequestNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentDueDateCalculator;

final class CreditRequestActivator
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly InstallmentDueDateCalculator     $installmentDueDateCalculator,
    ) {}

    public function execute(string $id, \DateTimeImmutable $now): CreditRequest
    {
        $creditRequest = $this->creditRequestRepository->findById($id);
        if ($creditRequest === null) {
            throw new CreditRequestNotFoundException();
        }

        $creditRequest->activate($now);

        $installments = $creditRequest->getInstallmentCollection()->toArray();
        usort($installments, static fn (Installment $a, Installment $b): int => $a->getPeriodNumber() <=> $b->getPeriodNumber());

        $dueDates = $this->installmentDueDateCalculator->calculate($now, count($installments));

        /**
         * @var int $index
         * @var Installment $installment
         */
        foreach ($installments as $index => $installment) {
            $installment->changeDueDate($dueDates[$index]);
        }

        $this->creditRequestRepository->save($creditRequest);

        return $creditRequest;
    }
}
