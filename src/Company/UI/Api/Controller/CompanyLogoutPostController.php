<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\CompanyLogout;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyLogoutPostController
{
    public function __construct(private readonly CompanyLogout $companyLogout) {}

    #[Route('/logout', name: 'company_logout', methods: ['POST'])]
    public function __invoke(): Response
    {
        $this->companyLogout->execute();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
