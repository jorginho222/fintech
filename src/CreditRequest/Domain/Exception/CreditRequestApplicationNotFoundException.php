<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CreditRequestApplicationNotFoundException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Solicitud de evaluación crediticia no encontrada.', 404);
    }
}
