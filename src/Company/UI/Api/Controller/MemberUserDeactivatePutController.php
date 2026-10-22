<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\MemberUserDeactivator;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberUserDeactivatePutController
{
    public function __construct(
        private readonly MemberUserDeactivator $memberUserDeactivator,
        private readonly CompanyUserSerializer $companyUserSerializer,
    ) {}

    #[Route('/user/{id}/deactivate', name: 'user_deactivate', methods: ['PUT'])]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse($this->companyUserSerializer->serialize($this->memberUserDeactivator->execute($id)), Response::HTTP_OK);
    }
}
