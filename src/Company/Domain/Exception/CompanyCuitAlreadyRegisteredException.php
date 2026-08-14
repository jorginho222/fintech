<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CompanyCuitAlreadyRegisteredException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('A company with that CUIT is already registered.', 409);
    }
}
