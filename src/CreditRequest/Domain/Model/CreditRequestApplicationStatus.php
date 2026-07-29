<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

enum CreditRequestApplicationStatus: string
{
    case EvaluationPending = 'evaluation_pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
