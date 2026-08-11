<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyCreateDto;
use App\Company\Application\UseCase\CompanyCreator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyPostController
{
    public function __construct(
        private readonly CompanyCreator     $companyCreator,
        private readonly ValidatorInterface $validator,
    )
    {
    }

    #[Route('/company', name: 'company_post', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CompanyCreateDto($request, $this->validator);
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
