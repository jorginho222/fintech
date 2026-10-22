<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\User;
use App\Company\Domain\Service\AuthToken;

final readonly class CompanyUserLoginResultDto
{
    public function __construct(
        public User      $user,
        public Company   $company,
        public AuthToken $accessToken,
        public AuthToken $refreshToken,
    ) {
    }
}
