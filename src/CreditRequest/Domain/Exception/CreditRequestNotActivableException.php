<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CreditRequestNotActivableException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('La solicitud de crédito no puede confirmarse en su estado actual.', 409);
    }
}
