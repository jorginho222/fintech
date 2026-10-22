<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MemberUserUpdateDto
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public readonly string $displayName;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->displayName = (string) ($data['displayName'] ?? '');

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
