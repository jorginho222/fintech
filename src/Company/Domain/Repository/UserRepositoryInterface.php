<?php

declare(strict_types=1);

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(string $id): ?User;

    /**
     * Admins and members share one identifier space, so this also answers "is it taken anywhere".
     */
    public function findByTaxIdentifier(string $taxIdentifier): ?User;

    public function findMemberByIdAndCompany(string $id, string $companyId): ?MemberUser;

    /** @return MemberUser[] */
    public function findMembersByCompany(string $companyId): array;

    public function countActiveMembersByCompany(string $companyId): int;
}
