<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class CreditRequestNotFoundException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Credit request not found.', 404);
    }
}
