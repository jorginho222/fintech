<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequestApplication;

interface CreditRequestApplicationRepositoryInterface
{
    public function save(CreditRequestApplication $creditRequestApplication): void;

    public function findById(string $id): ?CreditRequestApplication;

    /**
     * @return list<CreditRequestApplication>
     */
    public function search(string $companyId): array;

    /**
     * Statuses without applications are left out.
     *
     * @return array<string, int> application count keyed by status value
     */
    public function countByStatus(string $companyId): array;
}
