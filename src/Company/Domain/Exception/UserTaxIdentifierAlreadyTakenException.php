<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class UserTaxIdentifierAlreadyTakenException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Ya existe un usuario con ese CUIT/CUIL.', 409);
    }
}
