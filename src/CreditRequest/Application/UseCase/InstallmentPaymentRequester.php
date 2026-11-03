<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\Message\RequestInstallmentPaymentMessage;
use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class InstallmentPaymentRequester
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
        private readonly InstallmentPaymentNotifierInterface     $installmentPaymentNotifier,
        private readonly MessageBusInterface                     $messageBus,
    ) {}

    public function execute(string $installmentId): CreditRequest
    {
        $installment = $this->creditRequestRepository->findInstallmentById($installmentId);
        if (
            $installment === null
            || $installment->getCreditRequest()->getCompany()->getId()
                !== $this->authenticatedCompanyIdProvider->getCompanyId()
        ) {
            throw new InstallmentNotFoundException();
        }

        $installment->startPaymentAttempt(new \DateTimeImmutable());
        $this->creditRequestRepository->saveInstallment($installment);

        $this->messageBus->dispatch(new RequestInstallmentPaymentMessage($installment->getId()));
        $this->installmentPaymentNotifier->notifyPaymentProcessing($installment);

        return $installment->getCreditRequest();
    }
}
