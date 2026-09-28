<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Application\UseCase\CompanyTokenRefresher;
use App\Company\UI\Api\Serializer\CompanyAuthResultSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyTokenRefreshPostController
{
    public function __construct(
        private readonly CompanyTokenRefresher      $companyTokenRefresher,
        private readonly CompanyAuthResultSerializer $companyAuthResultSerializer,
        private readonly ValidatorInterface         $validator,
    ) {}

    #[Route('/refresh', name: 'company_token_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input  = new RefreshTokenDto($request, $this->validator);
        $result = $this->companyTokenRefresher->execute($input);

        return new JsonResponse($this->companyAuthResultSerializer->serialize($result), Response::HTTP_OK);
    }
}
