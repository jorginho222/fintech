<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

final class InsufficientScoreException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Company score is insufficient to obtain a credit request.', 422);
    }
}
