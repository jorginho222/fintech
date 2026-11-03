<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\RequestInstallmentPaymentMessage;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentPaymentWebhookClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RequestInstallmentPaymentMessageHandler
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface         $creditRequestRepository,
        private readonly InstallmentPaymentWebhookClientInterface $installmentPaymentWebhookClient,
    ) {}

    public function __invoke(RequestInstallmentPaymentMessage $message): void
    {
        $installment = $this->creditRequestRepository->findInstallmentById($message->getInstallmentId());
        if ($installment === null || !$installment->isPaymentProcessing()) {
            return;
        }

        $this->installmentPaymentWebhookClient->sendRequest(
            $installment->getId(),
            $installment->getCreditRequest()->getCompany()->getCuit(),
            $installment->getTotalAmount(),
        );
    }
}
