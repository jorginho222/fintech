<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Application\UseCase\MemberUserSearcher;
use App\Company\Domain\Model\MemberUser;
use App\Company\UI\Api\Serializer\CompanyUserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberUserSearchGetController
{
    public function __construct(
        private readonly MemberUserSearcher    $memberUserSearcher,
        private readonly CompanyUserSerializer $companyUserSerializer,
    ) {}

    #[Route('/user/search', name: 'user_search', methods: ['GET'], priority: 1)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            array_map(
                fn (MemberUser $member): array => $this->companyUserSerializer->serialize($member),
                $this->memberUserSearcher->execute(),
            ),
            Response::HTTP_OK,
        );
    }
}
