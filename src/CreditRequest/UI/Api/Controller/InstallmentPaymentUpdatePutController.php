<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultDto;
use App\CreditRequest\Application\Message\HandleInstallmentPaymentResultMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstallmentPaymentUpdatePutController
{
    public function __construct(
        private readonly ValidatorInterface  $validator,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route('/installment-payment-update', name: 'installment_payment_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Validated eagerly so a malformed webhook payload fails fast with a 4xx instead of being queued.
        new InstallmentPaymentResultDto($request, $this->validator);

        $this->messageBus->dispatch(new HandleInstallmentPaymentResultMessage($request->getContent()));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
