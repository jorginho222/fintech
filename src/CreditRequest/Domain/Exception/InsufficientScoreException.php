<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class InsufficientScoreException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Company score is insufficient to obtain a credit request.', 422);
    }
}
