<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Repository;

use App\CreditRequest\Domain\Model\CreditRequest;

interface CreditRequestRepositoryInterface
{
    public function save(CreditRequest $creditRequest): void;

    public function findById(string $id): ?CreditRequest;
}
