<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\CurrentUserProvider;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrentUserGetController
{
    public function __construct(
        private readonly CurrentUserProvider   $currentUserProvider,
        private readonly CompanyUserSerializer $companyUserSerializer,
    ) {}

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->companyUserSerializer->serialize($this->currentUserProvider->execute()), Response::HTTP_OK);
    }
}
