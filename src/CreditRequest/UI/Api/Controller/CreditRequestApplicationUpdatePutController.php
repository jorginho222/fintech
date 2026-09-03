<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\Message\HandleCreditRequestApplicationResultMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplicationUpdatePutController
{
    public function __construct(
        private readonly ValidatorInterface  $validator,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route('/credit-request-application-update', name: 'credit_request_application_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Validated eagerly so a malformed webhook payload fails fast with a 4xx instead of being queued.
        new CreditRequestApplicationResultDto($request, $this->validator);

        $this->messageBus->dispatch(new HandleCreditRequestApplicationResultMessage($request->getContent()));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
