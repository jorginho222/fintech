<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;

interface CreditRequestRepositoryInterface
{
    public function save(CreditRequest $creditRequest): void;

    public function saveInstallment(Installment $installment): void;

    public function findById(string $id): ?CreditRequest;

    /**
     * @return CreditRequest[]
     */
    public function findExpiredProposals(\DateTimeImmutable $now): array;

    /**
     * @return Installment[]
     */
    public function findWithOverdueInstallments(\DateTimeImmutable $now): array;
}
