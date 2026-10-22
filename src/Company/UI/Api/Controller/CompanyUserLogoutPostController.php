<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\DTO\CompanyUserLogoutDto;
use App\Company\Application\UseCase\CompanyUserLogout;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyUserLogoutPostController
{
    public function __construct(private readonly CompanyUserLogout $companyUserLogout) {}

    #[Route('/logout', name: 'company_user_logout', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $this->companyUserLogout->execute(new CompanyUserLogoutDto($request));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
