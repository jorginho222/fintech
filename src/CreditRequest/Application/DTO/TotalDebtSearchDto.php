<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TotalDebtSearchDto
{
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 12)]
    public readonly int $month;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public readonly int $year;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $this->month = (int) $request->query->get('month', 0);
        $this->year  = (int) $request->query->get('year', 0);

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }

    #[Assert\Callback]
    public function validateLimitIsNotBeforeCurrentMonth(ExecutionContextInterface $context): void
    {
        if ($this->month < 1 || $this->month > 12 || $this->year < 1) {
            return;
        }

        $today = new \DateTimeImmutable('today');
        $currentYear  = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');

        if ($this->year < $currentYear || ($this->year === $currentYear && $this->month < $currentMonth)) {
            $context->buildViolation('month/year must not be earlier than the current month and year.')
                ->atPath('month')
                ->addViolation();
        }
    }
}
