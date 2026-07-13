<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\InstallmentOverdueMarker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:mark-overdue-installments',
    description: 'Mark installments as overdue when their due date has passed',
)]
final class MarkOverdueInstallmentsCommand extends Command
{
    public function __construct(
        private readonly InstallmentOverdueMarker $installmentOverdueMarker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $overdueCount = $this->installmentOverdueMarker->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Marked %d installment(s) as overdue.', $overdueCount));

        return Command::SUCCESS;
    }
}
