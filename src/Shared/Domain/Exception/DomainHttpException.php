<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DomainHttpException extends \DomainException
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public function httpStatusCode(): int
    {
        return $this->httpStatus;
    }
}
