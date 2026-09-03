<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\Message\HandleCreditRequestApplicationResultMessage;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultHandler;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
final class HandleCreditRequestApplicationResultMessageHandler
{
    public function __construct(
        private readonly CreditRequestApplicationResultHandler $creditRequestApplicationResultHandler,
        private readonly ValidatorInterface                    $validator,
        private readonly TransactionManagerInterface            $transactionManager,
    ) {}

    public function __invoke(HandleCreditRequestApplicationResultMessage $message): void
    {
        $dto = new CreditRequestApplicationResultDto(
            new Request(content: $message->getPayload()),
            $this->validator,
        );

        $this->transactionManager->transactional(
            fn () => $this->creditRequestApplicationResultHandler->execute($dto),
        );
    }
}
