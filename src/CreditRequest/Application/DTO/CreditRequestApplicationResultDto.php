<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplicationResultDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [4])]
    public readonly string $applicationId;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{11}$/', message: 'CUIT must be exactly 11 digits.')]
    public readonly string $cuit;

    #[Assert\NotNull(message: 'evaluatedAt must be a valid date string.')]
    public readonly ?\DateTimeImmutable $evaluatedAt;

    #[Assert\Valid]
    public readonly CreditRequestApplicationResultDecisionDto $decision;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->applicationId = (string) ($data['applicationId'] ?? '');
        $this->cuit          = (string) ($data['cuit'] ?? '');
        $this->evaluatedAt   = $this->toDateTimeImmutable($data['evaluatedAt'] ?? null);
        $this->decision      = new CreditRequestApplicationResultDecisionDto(
            is_array($data['decision'] ?? null) ? $data['decision'] : [],
        );

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }

    private function toDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
