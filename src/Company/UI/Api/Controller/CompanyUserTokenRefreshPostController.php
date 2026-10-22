<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Application\UseCase\CompanyUserTokenRefresher;
use App\Company\UI\Api\Serializer\CompanyUserAuthResultSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyUserTokenRefreshPostController
{
    public function __construct(
        private readonly CompanyUserTokenRefresher       $companyUserTokenRefresher,
        private readonly CompanyUserAuthResultSerializer $companyUserAuthResultSerializer,
        private readonly ValidatorInterface              $validator,
    ) {}

    #[Route('/refresh', name: 'company_user_token_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input  = new RefreshTokenDto($request, $this->validator);
        $result = $this->companyUserTokenRefresher->execute($input);

        return new JsonResponse($this->companyUserAuthResultSerializer->serialize($result), Response::HTTP_OK);
    }
}
