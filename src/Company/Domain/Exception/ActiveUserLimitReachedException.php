<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class ActiveUserLimitReachedException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('La empresa ya alcanzó el máximo de usuarios activos.', 409);
    }
}
