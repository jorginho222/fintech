<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

use App\Company\Domain\Model\Company;

interface AuthTokenGeneratorInterface
{
    public function generateFor(Company $company): AuthToken;
}
