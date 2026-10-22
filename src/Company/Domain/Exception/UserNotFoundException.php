<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class UserNotFoundException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Usuario no encontrado.', 404);
    }
}
