<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

enum CreditRequestStatus: string
{
    case Proposal = 'proposal';
    case ProposalExpired = 'proposal_expired';
    case Active = 'active';
    case Paid = 'paid';
}
