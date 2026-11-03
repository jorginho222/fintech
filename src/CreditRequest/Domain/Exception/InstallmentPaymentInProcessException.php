<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Exception;

use App\Shared\Domain\Exception\DomainHttpException;

final class InstallmentPaymentInProcessException extends DomainHttpException
{
    public function __construct()
    {
        parent::__construct('El pago de esta cuota está siendo procesado. Espere la confirmación antes de volver a intentarlo.', 409);
    }
}
