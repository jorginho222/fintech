<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyUserLoginDto
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{11}$/')]
    public readonly string $taxIdentifier;

    #[Assert\NotBlank]
    public readonly string $password;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->taxIdentifier = (string) ($data['taxIdentifier'] ?? '');
        $this->password      = (string) ($data['password'] ?? '');

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
