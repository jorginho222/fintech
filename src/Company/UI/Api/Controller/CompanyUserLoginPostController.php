<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyUserLoginDto;
use App\Company\Application\UseCase\CompanyUserAuthenticator;
use App\Company\UI\Api\Serializer\CompanyUserAuthResultSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyUserLoginPostController
{
    public function __construct(
        private readonly CompanyUserAuthenticator        $companyUserAuthenticator,
        private readonly CompanyUserAuthResultSerializer $companyUserAuthResultSerializer,
        private readonly ValidatorInterface              $validator,
    )
    {
    }

    #[Route('/login', name: 'company_user_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input  = new CompanyUserLoginDto($request, $this->validator);
        $result = $this->companyUserAuthenticator->execute($input);

        return new JsonResponse($this->companyUserAuthResultSerializer->serialize($result), Response::HTTP_OK);
    }
}
