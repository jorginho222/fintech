<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Application\UseCase\CreditRequestEvaluator;
use App\CreditRequest\Domain\Exception\ExceededAmountException;
use App\CreditRequest\Domain\Exception\InsufficientScoreException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class CreditRequestEvaluatePostController
{
    public function __construct(
        private readonly CreditRequestEvaluator $creditRequestEvaluator,
        private readonly \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
    ) {}

    #[Route('/api/credit_request/evaluate', name: 'credit_request_evaluate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $input = new CreditRequestEvaluateDto($request, $this->validator);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->creditRequestEvaluator->execute($input);
        } catch (InsufficientScoreException|ExceededAmountException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['status' => 'approved'], Response::HTTP_OK);
    }
}
