<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\ExpireCreditRequestProposalsMessage;
use App\CreditRequest\Application\UseCase\CreditRequestProposalExpirer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExpireCreditRequestProposalsMessageHandler
{
    public function __construct(
        private readonly CreditRequestProposalExpirer $creditRequestProposalExpirer,
    ) {}

    public function __invoke(ExpireCreditRequestProposalsMessage $message): void
    {
        $this->creditRequestProposalExpirer->execute(new \DateTimeImmutable());
    }
}
