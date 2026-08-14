<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

interface AuthenticatedCompanyIdProviderInterface
{
    public function getCompanyId(): string;
}
