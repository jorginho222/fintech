<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class PasswordChangeNotAllowedException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('Este usuario no tiene permitido cambiar la contraseña.', 409);
    }
}
