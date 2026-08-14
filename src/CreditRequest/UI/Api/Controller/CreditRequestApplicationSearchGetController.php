<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\UseCase\CreditRequestApplicationSearcher;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\UI\Api\Serializer\CreditRequestApplicationSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreditRequestApplicationSearchGetController
{
    public function __construct(
        private readonly CreditRequestApplicationSearcher   $creditRequestApplicationSearcher,
        private readonly CreditRequestApplicationSerializer $creditRequestApplicationSerializer,
    ) {}

    #[Route('/credit-request-application/search', name: 'credit_request_application_search', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $creditRequestApplications = $this->creditRequestApplicationSearcher->execute();

        return new JsonResponse(
            array_map(
                fn (CreditRequestApplication $application): array => $this->creditRequestApplicationSerializer->serialize($application),
                $creditRequestApplications,
            ),
            Response::HTTP_OK,
        );
    }
}
