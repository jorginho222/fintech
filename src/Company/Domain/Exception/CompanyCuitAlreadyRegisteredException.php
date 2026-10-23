<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CompanyCuitAlreadyRegisteredException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Ya existe una empresa registrada con ese CUIT.', 409);
    }
}
