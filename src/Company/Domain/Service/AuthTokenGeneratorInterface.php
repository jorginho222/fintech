<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

use App\Company\Domain\Model\Company;

interface AuthTokenGeneratorInterface
{
    public function generateAccessTokenFor(Company $company): AuthToken;

    public function generateRefreshTokenFor(Company $company): AuthToken;
}
