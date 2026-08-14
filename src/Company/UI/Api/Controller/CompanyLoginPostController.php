<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyLoginDto;
use App\Company\Application\UseCase\CompanyAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyLoginPostController
{
    public function __construct(
        private readonly CompanyAuthenticator $companyAuthenticator,
        private readonly ValidatorInterface   $validator,
    )
    {
    }

    #[Route('/login', name: 'company_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input  = new CompanyLoginDto($request, $this->validator);
        $result = $this->companyAuthenticator->execute($input);

        return new JsonResponse([
            'token' => $result->token->value,
            'expiresAt' => $result->token->expiresAt->format(\DateTimeInterface::ATOM),
            'company' => [
                'id' => $result->company->getId(),
                'socialReason' => $result->company->getSocialReason(),
                'cuit' => $result->company->getCuit(),
                'email' => $result->company->getEmail(),
                'taxStatus' => $result->company->getTaxStatus()->value,
            ],
        ], Response::HTTP_OK);
    }
}
