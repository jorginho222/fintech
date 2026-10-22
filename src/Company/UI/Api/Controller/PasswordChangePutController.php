<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\PasswordChangeDto;
use App\Company\Application\UseCase\PasswordChanger;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PasswordChangePutController
{
    public function __construct(
        private readonly PasswordChanger       $passwordChanger,
        private readonly CompanyUserSerializer $companyUserSerializer,
        private readonly ValidatorInterface    $validator,
    ) {}

    #[Route('/me/password', name: 'me_password_change', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->passwordChanger->execute(new PasswordChangeDto($request, $this->validator));

        return new JsonResponse($this->companyUserSerializer->serialize($user), Response::HTTP_OK);
    }
}
