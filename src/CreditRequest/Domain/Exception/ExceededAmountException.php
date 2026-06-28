<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class ExceededAmountException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Requested amount exceeds the maximum allowed for this company.', 422);
    }
}
