<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\TimeoutInstallmentPaymentAttemptsMessage;
use App\CreditRequest\Application\UseCase\InstallmentPaymentAttemptTimeoutMarker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TimeoutInstallmentPaymentAttemptsMessageHandler
{
    public function __construct(
        private readonly InstallmentPaymentAttemptTimeoutMarker $installmentPaymentAttemptTimeoutMarker,
    ) {}

    public function __invoke(TimeoutInstallmentPaymentAttemptsMessage $message): void
    {
        $this->installmentPaymentAttemptTimeoutMarker->execute(new \DateTimeImmutable());
    }
}
