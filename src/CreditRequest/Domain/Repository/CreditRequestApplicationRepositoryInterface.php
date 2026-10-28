<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequestApplication;

interface CreditRequestApplicationRepositoryInterface
{
    public function save(CreditRequestApplication $creditRequestApplication): void;

    public function findById(string $id): ?CreditRequestApplication;

    public function findByCompanyAndReference(string $companyId, string $reference): ?CreditRequestApplication;

    /**
     * @return list<CreditRequestApplication>
     */
    public function search(string $companyId): array;
}
