<?php

declare(strict_types=1);

namespace App\Company\UI\Console;

use App\Company\Application\UseCase\RevokedTokenExpiredDeleter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:company:delete-expired-revoked-tokens',
    description: 'Delete revoked token entries whose underlying JWT has already expired',
)]
final class DeleteExpiredRevokedTokensCommand extends Command
{
    public function __construct(
        private readonly RevokedTokenExpiredDeleter $revokedTokenExpiredDeleter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deletedCount = $this->revokedTokenExpiredDeleter->execute(new \DateTimeImmutable());

        $output->writeln(sprintf('Deleted %d expired revoked token(s).', $deletedCount));

        return Command::SUCCESS;
    }
}
