<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CreditRequestApplicationReferenceAlreadyExistsException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Ya existe una solicitud de crédito con esa referencia.', 409);
    }
}
