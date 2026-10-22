<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Serializer;

use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\User;

/**
 * One shape for admins and members alike, used by login/refresh, /me and member management.
 */
final class CompanyUserSerializer
{
    public function serialize(User $user): array
    {
        return [
            'id' => $user->getId(),
            'type' => $user->isAdmin() ? 'admin' : 'member',
            'taxIdentifier' => $user->getTaxIdentifier(),
            'displayName' => $user instanceof MemberUser ? $user->getDisplayName() : null,
            'active' => $user->isActive(),
            'mustChangePassword' => $user->mustChangePassword(),
            'permissions' => array_map(static fn (Permission $permission): string => $permission->value, $user->permissions()),
        ];
    }
}
