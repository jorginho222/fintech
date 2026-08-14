<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestApplicationCreateDto;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationHandler;
use App\CreditRequest\UI\Api\Serializer\CreditRequestApplicationSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplicationCreatePostController
{
    public function __construct(
        private readonly CreditRequestApplicationHandler    $creditRequestApplicationHandler,
        private readonly ValidatorInterface                 $validator,
        private readonly CreditRequestApplicationSerializer $creditRequestApplicationSerializer,
    )
    {
    }

    #[Route('/credit-request/apply', name: 'credit_request_apply', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestApplicationCreateDto($request, $this->validator);
        $creditRequestApplication = $this->creditRequestApplicationHandler->execute($input);

        return new JsonResponse($this->creditRequestApplicationSerializer->serialize($creditRequestApplication), Response::HTTP_CREATED);
    }
}
