<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Service\AuthToken;

final readonly class CompanyLoginResultDto
{
    public function __construct(
        public Company   $company,
        public AuthToken $token,
    ) {
    }
}
