<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Application\Validation\StrongPassword;
use App\Company\Domain\Model\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserCreateDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [4])]
    public readonly string $id;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{11}$/')]
    public readonly string $taxIdentifier;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public readonly string $displayName;

    #[StrongPassword]
    public readonly string $password;

    /** @var string[]|null */
    #[Assert\NotNull]
    #[Assert\All([new Assert\Choice(callback: [Permission::class, 'values'])])]
    public readonly ?array $permissions;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->id            = (string) ($data['id'] ?? '');
        $this->taxIdentifier = (string) ($data['taxIdentifier'] ?? '');
        $this->displayName   = (string) ($data['displayName'] ?? '');
        $this->password      = (string) ($data['password'] ?? '');
        $this->permissions   = is_array($data['permissions'] ?? null) ? array_values($data['permissions']) : null;

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
