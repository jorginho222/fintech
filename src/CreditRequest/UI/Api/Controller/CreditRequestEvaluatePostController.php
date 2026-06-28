<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Application\UseCase\CreditRequestEvaluator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestEvaluatePostController
{
    public function __construct(
        private readonly CreditRequestEvaluator $creditRequestEvaluator,
        private readonly ValidatorInterface      $validator,
    ) {}

    #[Route('/api/credit-request/evaluate', name: 'credit_request_evaluate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestEvaluateDto($request, $this->validator);
        $this->creditRequestEvaluator->execute($input);

        return new JsonResponse(['status' => 'approved'], Response::HTTP_OK);
    }
}
