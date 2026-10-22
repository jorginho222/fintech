<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\MemberUserUpdateDto;
use App\Company\Application\UseCase\MemberUserUpdater;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserUpdatePutController
{
    public function __construct(
        private readonly MemberUserUpdater     $memberUserUpdater,
        private readonly CompanyUserSerializer $companyUserSerializer,
        private readonly ValidatorInterface    $validator,
    ) {}

    #[Route('/user/{id}', name: 'user_update', methods: ['PUT'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $member = $this->memberUserUpdater->execute($id, new MemberUserUpdateDto($request, $this->validator));

        return new JsonResponse($this->companyUserSerializer->serialize($member), Response::HTTP_OK);
    }
}
