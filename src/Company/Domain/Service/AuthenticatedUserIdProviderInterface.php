<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

interface AuthenticatedUserIdProviderInterface
{
    public function getUserId(): string;
}
