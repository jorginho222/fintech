<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

final class ExceededAmountException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Requested amount exceeds the maximum allowed for this company.', 422);
    }
}
