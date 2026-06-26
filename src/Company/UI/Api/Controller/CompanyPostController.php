<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyCreateDto;
use App\Company\Application\UseCase\CompanyCreator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyPostController
{
    public function __construct(
        private readonly CompanyCreator     $companyCreator,
        private readonly ValidatorInterface $validator,
    ) {}


    #[Route('/api/company', name: 'company_post', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $input = new CompanyCreateDto($request, $this->validator);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->companyCreator->execute($input);

        return new JsonResponse([
            'id' => $input->id,
            'socialReason' => $input->socialReason,
            'cuit' => $input->cuit,
            'email' => $input->email,
            'taxStatus' => $input->taxStatus,
        ], Response::HTTP_CREATED);
    }
}
