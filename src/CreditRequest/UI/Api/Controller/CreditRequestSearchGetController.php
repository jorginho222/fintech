<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\UseCase\CreditRequestSearcher;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditRequestSearchGetController
{
    public function __construct(
        private readonly CreditRequestSearcher   $creditRequestSearcher,
        private readonly CreditRequestSerializer $creditRequestSerializer,
    ) {}

    #[Route('/credit-request/search', name: 'credit_request_search', methods: ['GET'], priority: 1)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            array_map(
                fn (CreditRequest $creditRequest): array => $this->creditRequestSerializer->serialize($creditRequest),
                $this->creditRequestSearcher->execute(),
            ),
            Response::HTTP_OK,
        );
    }
}
