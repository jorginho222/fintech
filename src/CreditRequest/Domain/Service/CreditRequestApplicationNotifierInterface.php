<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Model\CreditRequestApplication;

interface CreditRequestApplicationNotifierInterface
{
    public function notifyRequestSent(CreditRequestApplication $creditRequestApplication): void;

    public function notifyApproved(CreditRequestApplication $creditRequestApplication): void;

    public function notifyRejected(CreditRequestApplication $creditRequestApplication): void;
}
