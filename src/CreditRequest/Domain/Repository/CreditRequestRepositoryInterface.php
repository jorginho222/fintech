<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;

interface CreditRequestRepositoryInterface
{
    public function save(CreditRequest $creditRequest): void;

    public function delete(CreditRequest $creditRequest): void;

    public function saveInstallment(Installment $installment): void;

    public function findById(string $id): ?CreditRequest;

    public function findInstallmentById(string $id): ?Installment;

    /**
     * @return CreditRequest[]
     */
    public function search(string $companyId, CreditRequestStatus $status): array;

    /**
     * @return CreditRequest[]
     */
    public function findExpiredProposals(\DateTimeImmutable $now): array;

    /**
     * @return CreditRequest[]
     */
    public function findExpiredProposalsForDeletion(\DateTimeImmutable $before): array;

    /**
     * @return Installment[]
     */
    public function findWithOverdueInstallments(\DateTimeImmutable $now): array;

    /**
     * When $includeOverdueBeforePeriodStart is true, overdue installments are always included
     * regardless of how far in the past their due date is; pending installments still require
     * a due date on or after $periodStart. Both are bounded above by $periodEnd (exclusive).
     *
     * @return Installment[]
     */
    public function findPendingInstallmentsToPay(
        string $companyId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        bool $includeOverdueBeforePeriodStart = false,
    ): array;
}
