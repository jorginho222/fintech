<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\InstallmentPaymentAttemptTimeoutMarker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:timeout-payment-attempts',
    description: 'Mark installment payment attempts as timed out after 72 business hours without a result',
)]
final class TimeoutInstallmentPaymentAttemptsCommand extends Command
{
    public function __construct(
        private readonly InstallmentPaymentAttemptTimeoutMarker $installmentPaymentAttemptTimeoutMarker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timedOutCount = $this->installmentPaymentAttemptTimeoutMarker->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Marked %d payment attempt(s) as timed out.', $timedOutCount));

        return Command::SUCCESS;
    }
}
