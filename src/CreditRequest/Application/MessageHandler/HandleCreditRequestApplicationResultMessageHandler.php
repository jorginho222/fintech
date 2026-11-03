<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\Message\HandleCreditRequestApplicationResultMessage;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultHandler;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultNotifier;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
final class HandleCreditRequestApplicationResultMessageHandler
{
    public function __construct(
        private readonly CreditRequestApplicationResultHandler $creditRequestApplicationResultHandler,
        private readonly CreditRequestApplicationResultNotifier $creditRequestApplicationResultNotifier,
        private readonly ValidatorInterface                    $validator,
        private readonly TransactionManagerInterface            $transactionManager,
    ) {}

    public function __invoke(HandleCreditRequestApplicationResultMessage $message): void
    {
        $dto = new CreditRequestApplicationResultDto(
            new Request(content: $message->getPayload()),
            $this->validator,
        );

        $creditRequestApplication = $this->transactionManager->transactional(
            fn () => $this->creditRequestApplicationResultHandler->execute($dto),
        );

        if ($creditRequestApplication === null) {
            return;
        }

        // Outside the transaction, so the email is only queued once the result has been committed.
        $this->creditRequestApplicationResultNotifier->execute($creditRequestApplication);
    }
}
