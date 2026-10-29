<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationStatsCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CreditRequestApplicationStatsGetController
{
    public function __construct(
        private readonly CreditRequestApplicationStatsCalculator $creditRequestApplicationStatsCalculator,
    ) {}

    #[IsGranted(Permission::CreditRequestApplicationList->value)]
    #[Route('/credit-request-application/stats', name: 'credit_request_application_stats', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $stats = $this->creditRequestApplicationStatsCalculator->execute();

        return new JsonResponse([
            'total'             => $stats->total,
            'approved'          => $stats->approved,
            'rejected'          => $stats->rejected,
            'evaluationPending' => $stats->evaluationPending,
        ], Response::HTTP_OK);
    }
}
