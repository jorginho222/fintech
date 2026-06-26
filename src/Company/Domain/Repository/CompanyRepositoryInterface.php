<?php

declare(strict_types=1);

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\Company;

interface CompanyRepositoryInterface
{
    public function save(Company $company): void;

    public function findById(string $id): ?Company;

    public function findAll(): array;

    public function delete(Company $company): void;
}
