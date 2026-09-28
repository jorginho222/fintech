<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class InvalidRefreshTokenException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid refresh token.', 401);
    }
}
