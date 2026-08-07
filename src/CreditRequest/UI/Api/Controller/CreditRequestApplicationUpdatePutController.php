<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultHandler;
use App\CreditRequest\UI\Api\Serializer\CreditRequestApplicationSerializer;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplicationUpdatePutController
{
    public function __construct(
        private readonly ValidatorInterface                   $validator,
        private readonly CreditRequestApplicationResultHandler $creditRequestApplicationResultHandler,
        private readonly CreditRequestApplicationSerializer    $creditRequestApplicationSerializer,
        private readonly TransactionManagerInterface           $transactionManager,
    ) {}

    #[Route('/api/credit-request-application-update', name: 'credit_request_application_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestApplicationResultDto($request, $this->validator);

        $creditRequestApplication = $this->transactionManager->transactional(
            fn () => $this->creditRequestApplicationResultHandler->execute($input),
        );

        return new JsonResponse($this->creditRequestApplicationSerializer->serialize($creditRequestApplication), Response::HTTP_OK);
    }
}
