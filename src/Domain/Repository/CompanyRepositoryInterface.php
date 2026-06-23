<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Company;

interface CompanyRepositoryInterface
{
    public function save(Company $company): void;
    public function findById(string $id): ?Company;
    /** @return Company[] */
    public function findAll(): array;
    public function delete(Company $company): void;
}
