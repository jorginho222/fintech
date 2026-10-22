<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\MemberUserPermissionsUpdateDto;
use App\Company\Application\UseCase\MemberUserPermissionsUpdater;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserPermissionsUpdatePutController
{
    public function __construct(
        private readonly MemberUserPermissionsUpdater $memberUserPermissionsUpdater,
        private readonly CompanyUserSerializer        $companyUserSerializer,
        private readonly ValidatorInterface           $validator,
    ) {}

    #[Route('/user/{id}/permissions', name: 'user_permissions_update', methods: ['PUT'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $member = $this->memberUserPermissionsUpdater->execute($id, new MemberUserPermissionsUpdateDto($request, $this->validator));

        return new JsonResponse($this->companyUserSerializer->serialize($member), Response::HTTP_OK);
    }
}
