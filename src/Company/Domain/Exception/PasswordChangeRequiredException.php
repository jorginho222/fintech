<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class PasswordChangeRequiredException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Debe cambiar su contraseña para continuar.', 403);
    }
}
