<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\UseCase\CreditRequestFinder;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditRequestFindGetController
{
    public function __construct(
        private readonly CreditRequestFinder     $creditRequestFinder,
        private readonly CreditRequestSerializer $creditRequestSerializer,
    ) {}

    #[Route('/credit-request/{id}', name: 'credit_request_find', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $creditRequest = $this->creditRequestFinder->execute($id);

        return new JsonResponse(
            $this->creditRequestSerializer->serialize($creditRequest),
            Response::HTTP_OK,
        );
    }
}
