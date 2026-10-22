<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\MemberUserCreateDto;
use App\Company\Application\UseCase\MemberUserCreator;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserCreatePostController
{
    public function __construct(
        private readonly MemberUserCreator     $memberUserCreator,
        private readonly CompanyUserSerializer $companyUserSerializer,
        private readonly ValidatorInterface    $validator,
    ) {}

    #[Route('/user', name: 'user_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $member = $this->memberUserCreator->execute(new MemberUserCreateDto($request, $this->validator));

        return new JsonResponse($this->companyUserSerializer->serialize($member), Response::HTTP_CREATED);
    }
}
