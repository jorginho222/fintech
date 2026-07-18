<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\DeleteExpiredCreditRequestProposalsMessage;
use App\CreditRequest\Application\UseCase\CreditRequestExpiredProposalDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteExpiredCreditRequestProposalsMessageHandler
{
    public function __construct(
        private readonly CreditRequestExpiredProposalDeleter $creditRequestExpiredProposalDeleter,
    ) {}

    public function __invoke(DeleteExpiredCreditRequestProposalsMessage $message): void
    {
        $this->creditRequestExpiredProposalDeleter->execute(new \DateTimeImmutable());
    }
}
