<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Console;

use App\CreditRequest\Application\UseCase\CreditRequestExpiredProposalDeleter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:credit-request:delete-expired-proposals',
    description: 'Delete expired credit request proposals past their retention period',
)]
final class DeleteExpiredCreditRequestProposalsCommand extends Command
{
    public function __construct(
        private readonly CreditRequestExpiredProposalDeleter $creditRequestExpiredProposalDeleter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deletedCount = $this->creditRequestExpiredProposalDeleter->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Deleted %d expired credit request proposal(s).', $deletedCount));

        return Command::SUCCESS;
    }
}
