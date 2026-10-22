<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

/**
 * Not a 401: the caller is authenticated, and clients treat 401 as an expired session.
 */
final class InvalidCurrentPasswordException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('La contraseña actual es incorrecta.', 422);
    }
}
