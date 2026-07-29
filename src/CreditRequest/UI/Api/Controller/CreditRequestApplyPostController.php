<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestApplyDto;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationService;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplyPostController
{
    public function __construct(
        private readonly CreditRequestApplicationService $creditRequestApplicationService,
        private readonly ValidatorInterface      $validator,
        private readonly CreditRequestSerializer $creditRequestSerializer,
    )
    {
    }

    #[Route('/api/credit-request/apply', name: 'credit_request_apply', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestApplyDto($request, $this->validator);
        $creditRequest = $this->creditRequestApplicationService->execute($input);

        return new JsonResponse($this->creditRequestSerializer->serialize($creditRequest), Response::HTTP_OK);
    }
}
