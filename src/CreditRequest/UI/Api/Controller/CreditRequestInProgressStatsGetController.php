<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use App\CreditRequest\Application\UseCase\CreditRequestInProgressStatsCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CreditRequestInProgressStatsGetController
{
    public function __construct(
        private readonly CreditRequestInProgressStatsCalculator $creditRequestInProgressStatsCalculator,
    ) {}

    #[IsGranted(Permission::CreditRequestList->value)]
    #[Route('/credit-request/stats', name: 'credit_request_stats', methods: ['GET'], priority: 1)]
    public function __invoke(): JsonResponse
    {
        $stats = $this->creditRequestInProgressStatsCalculator->execute();

        $response = new JsonResponse(null, Response::HTTP_OK);
        // Keeps whole percentages as floats (0.0, 100.0) so the field type never changes.
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);

        return $response->setData([
            'total'          => $stats->total,
            'paidPercentage' => $stats->paidPercentage,
        ]);
    }
}
