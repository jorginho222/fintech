<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\UseCase\CreditRequestActivator;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditRequestConfirmPutController
{
    public function __construct(
        private readonly CreditRequestActivator $creditRequestActivator,
        private readonly CreditRequestSerializer $creditRequestSerializer,
    ) {}

    #[Route('/api/credit-request/{id}/confirm', name: 'credit_request_confirm', methods: ['PUT'])]
    public function __invoke(string $id): JsonResponse
    {
        $creditRequest = $this->creditRequestActivator->execute($id, new \DateTimeImmutable());

        return new JsonResponse($this->creditRequestSerializer->serialize($creditRequest), Response::HTTP_OK);
    }
}
