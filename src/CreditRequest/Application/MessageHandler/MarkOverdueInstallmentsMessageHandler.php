<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\MarkOverdueInstallmentsMessage;
use App\CreditRequest\Application\UseCase\InstallmentOverdueMarker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MarkOverdueInstallmentsMessageHandler
{
    public function __construct(
        private readonly InstallmentOverdueMarker $installmentOverdueMarker,
    ) {}

    public function __invoke(MarkOverdueInstallmentsMessage $message): void
    {
        $this->installmentOverdueMarker->execute(new \DateTimeImmutable());
    }
}
