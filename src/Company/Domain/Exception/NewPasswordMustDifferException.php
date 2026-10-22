<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class NewPasswordMustDifferException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('La nueva contraseña debe ser distinta de la actual.', 422);
    }
}
