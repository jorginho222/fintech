<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

interface TransactionManagerInterface
{
    public function transactional(callable $callback): mixed;
}
