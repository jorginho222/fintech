<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\CreditRequestProposalExpirer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:expire-proposals',
    description: 'Expire credit request proposals that are past their approval limit date',
)]
final class ExpireCreditRequestProposalsCommand extends Command
{
    public function __construct(
        private readonly CreditRequestProposalExpirer $creditRequestProposalExpirer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredCount = $this->creditRequestProposalExpirer->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Expired %d credit request proposal(s).', $expiredCount));

        return Command::SUCCESS;
    }
}
