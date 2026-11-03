<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

enum InstallmentPaymentAttemptStatus: string
{
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ProcessingFailed = 'processing_failed';
    case ProcessingTimeout = 'processing_timeout';
}
