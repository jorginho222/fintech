<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Domain\Model\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserPermissionsUpdateDto
{
    /**
     * Required even when empty, so a missing key never silently revokes every permission.
     *
     * @var string[]|null
     */
    #[Assert\NotNull]
    #[Assert\All([new Assert\Choice(callback: [Permission::class, 'values'])])]
    public readonly ?array $permissions;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->permissions = is_array($data['permissions'] ?? null) ? array_values($data['permissions']) : null;

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }

    /** @return Permission[] */
    public function permissions(): array
    {
        return array_map(static fn (string $value): Permission => Permission::from($value), $this->permissions ?? []);
    }
}
