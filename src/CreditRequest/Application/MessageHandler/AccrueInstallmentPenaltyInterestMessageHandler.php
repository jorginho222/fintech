<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\AccrueInstallmentPenaltyInterestMessage;
use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestAccruer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AccrueInstallmentPenaltyInterestMessageHandler
{
    public function __construct(
        private readonly InstallmentPenaltyInterestAccruer $installmentPenaltyInterestAccruer,
    ) {}

    public function __invoke(AccrueInstallmentPenaltyInterestMessage $message): void
    {
        $this->installmentPenaltyInterestAccruer->execute(new \DateTimeImmutable());
    }
}
