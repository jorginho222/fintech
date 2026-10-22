<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

use App\Company\Domain\Model\User;

interface AuthTokenGeneratorInterface
{
    public function generateAccessTokenFor(User $user): AuthToken;

    public function generateRefreshTokenFor(User $user): AuthToken;
}
