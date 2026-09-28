<?php

declare(strict_types=1);

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\RevokedToken;

interface RevokedTokenRepositoryInterface
{
    public function save(RevokedToken $revokedToken): void;

    public function existsByJti(string $jti): bool;
}
