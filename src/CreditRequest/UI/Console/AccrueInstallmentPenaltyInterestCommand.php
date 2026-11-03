<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestAccruer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:accrue-penalty-interest',
    description: 'Charge late-payment interest on overdue installments for the days since their last calculation',
)]
final class AccrueInstallmentPenaltyInterestCommand extends Command
{
    public function __construct(
        private readonly InstallmentPenaltyInterestAccruer $installmentPenaltyInterestAccruer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accruedCount = $this->installmentPenaltyInterestAccruer->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Charged late-payment interest on %d installment(s).', $accruedCount));

        return Command::SUCCESS;
    }
}
