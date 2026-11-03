<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\SendInstallmentDueDateRemindersMessage;
use App\CreditRequest\Application\UseCase\InstallmentDueDateReminder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendInstallmentDueDateRemindersMessageHandler
{
    public function __construct(
        private readonly InstallmentDueDateReminder $installmentDueDateReminder,
    ) {}

    public function __invoke(SendInstallmentDueDateRemindersMessage $message): void
    {
        $this->installmentDueDateReminder->execute(new \DateTimeImmutable());
    }
}
