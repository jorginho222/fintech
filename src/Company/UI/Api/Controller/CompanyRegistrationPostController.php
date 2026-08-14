<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyRegistrationDto;
use App\Company\Application\UseCase\CompanyRegistrator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyRegistrationPostController
{
    public function __construct(
        private readonly CompanyRegistrator $companyRegistrator,
        private readonly ValidatorInterface $validator,
    )
    {
    }

    #[Route('/company_registration', name: 'company_registration_post', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CompanyRegistrationDto($request, $this->validator);
        $this->companyRegistrator->execute($input);

        return new JsonResponse([
            'id' => $input->id,
            'socialReason' => $input->socialReason,
            'cuit' => $input->cuit,
            'email' => $input->email,
            'taxStatus' => $input->taxStatus,
        ], Response::HTTP_CREATED);
    }
}
