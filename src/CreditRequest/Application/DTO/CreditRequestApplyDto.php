<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplyDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [4])]
    public readonly string $companyId;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public readonly int $amount;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public readonly int $installmentQuantity;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->companyId           = (string) ($data['companyId'] ?? '');
        $this->amount              = (int) ($data['amount'] ?? 0);
        $this->installmentQuantity = (int) ($data['installmentQuantity'] ?? 0);

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
