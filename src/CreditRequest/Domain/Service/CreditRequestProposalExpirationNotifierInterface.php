<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Model\CreditRequest;

interface CreditRequestProposalExpirationNotifierInterface
{
    public function notify(CreditRequest $creditRequest): void;
}
