<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\InstallmentDueDateReminder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:send-due-date-reminders',
    description: 'Email a reminder for installments that are about to reach their due date',
)]
final class SendInstallmentDueDateRemindersCommand extends Command
{
    public function __construct(
        private readonly InstallmentDueDateReminder $installmentDueDateReminder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reminderCount = $this->installmentDueDateReminder->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Sent %d due date reminder(s).', $reminderCount));

        return Command::SUCCESS;
    }
}
