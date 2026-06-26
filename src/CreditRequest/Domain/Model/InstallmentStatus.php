<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
