<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberUserPermissionCatalogueGetController
{
    #[Route('/user/permission-catalogue', name: 'user_permission_catalogue', methods: ['GET'], priority: 1)]
    public function __invoke(): JsonResponse
    {
        $groups = [];
        foreach (Permission::cases() as $permission) {
            $groups[$permission->resource()][] = $permission->value;
        }

        return new JsonResponse(
            array_map(
                static fn (string $resource, array $permissions): array => ['resource' => $resource, 'permissions' => $permissions],
                array_keys($groups),
                $groups,
            ),
            Response::HTTP_OK,
        );
    }
}
