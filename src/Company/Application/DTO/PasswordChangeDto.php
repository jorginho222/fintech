<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Application\Validation\StrongPassword;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PasswordChangeDto
{
    #[Assert\NotBlank]
    public readonly string $currentPassword;

    #[StrongPassword]
    public readonly string $newPassword;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->currentPassword = (string) ($data['currentPassword'] ?? '');
        $this->newPassword     = (string) ($data['newPassword'] ?? '');

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
