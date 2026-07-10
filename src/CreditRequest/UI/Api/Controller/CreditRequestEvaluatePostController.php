<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Application\UseCase\CreditRequestEvaluator;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestEvaluatePostController
{
    public function __construct(
        private readonly CreditRequestEvaluator  $creditRequestEvaluator,
        private readonly ValidatorInterface      $validator,
        private readonly CreditRequestSerializer $creditRequestSerializer,
    )
    {
    }

    #[Route('/api/credit-request/evaluate', name: 'credit_request_evaluate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestEvaluateDto($request, $this->validator);
        $creditRequest = $this->creditRequestEvaluator->execute($input);

        return new JsonResponse($this->creditRequestSerializer->serialize($creditRequest), Response::HTTP_OK);
    }
}
