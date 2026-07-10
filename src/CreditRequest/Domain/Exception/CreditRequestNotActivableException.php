<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CreditRequestNotActivableException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Credit request cannot be confirmed from its current status.', 409);
    }
}
