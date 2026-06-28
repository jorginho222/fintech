<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CompanyNotFoundException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Company not found.', 404);
    }
}
