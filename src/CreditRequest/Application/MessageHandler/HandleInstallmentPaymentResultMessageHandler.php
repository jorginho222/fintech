<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultDto;
use App\CreditRequest\Application\Message\HandleInstallmentPaymentResultMessage;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultHandler;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultNotifier;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
final class HandleInstallmentPaymentResultMessageHandler
{
    public function __construct(
        private readonly InstallmentPaymentResultHandler  $installmentPaymentResultHandler,
        private readonly InstallmentPaymentResultNotifier $installmentPaymentResultNotifier,
        private readonly ValidatorInterface               $validator,
        private readonly TransactionManagerInterface      $transactionManager,
    ) {}

    public function __invoke(HandleInstallmentPaymentResultMessage $message): void
    {
        $dto = new InstallmentPaymentResultDto(
            new Request(content: $message->getPayload()),
            $this->validator,
        );

        $outcome = $this->transactionManager->transactional(
            fn () => $this->installmentPaymentResultHandler->execute($dto),
        );

        // Outside the transaction, so the emails are only queued once the result has been committed.
        $this->installmentPaymentResultNotifier->execute($outcome);
    }
}
