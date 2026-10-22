<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\MemberUserActivator;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberUserActivatePutController
{
    public function __construct(
        private readonly MemberUserActivator   $memberUserActivator,
        private readonly CompanyUserSerializer $companyUserSerializer,
    ) {}

    #[Route('/user/{id}/activate', name: 'user_activate', methods: ['PUT'])]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse($this->companyUserSerializer->serialize($this->memberUserActivator->execute($id)), Response::HTTP_OK);
    }
}
