<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

enum CreditRequestStatus: string
{
    case Draft = 'draft';
    case ScoringPending = 'scoring_pending';
    case ManualReview = 'manual_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Active = 'active';
    case Paid = 'paid';
}
