<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\MemberUserFinder;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberUserFindGetController
{
    public function __construct(
        private readonly MemberUserFinder      $memberUserFinder,
        private readonly CompanyUserSerializer $companyUserSerializer,
    ) {}

    #[Route('/user/{id}', name: 'user_find', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        return new JsonResponse($this->companyUserSerializer->serialize($this->memberUserFinder->execute($id)), Response::HTTP_OK);
    }
}
