<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreditRequestApplicationResultDecisionDto
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['APPROVED', 'REJECTED'])]
    public readonly string $status;

    #[Assert\NotNull(message: 'score is required.')]
    #[Assert\Type(type: 'numeric', message: 'score must be numeric.')]
    public readonly int|float|string|null $score;

    public readonly ?float $assignedTna;

    public readonly ?string $rejectionReason;

    public function __construct(array $data)
    {
        $this->status = (string)($data['status'] ?? '');
        $this->score = $data['score'] ?? null;
        $this->assignedTna = isset($data['assignedTna']) ? (float)$data['assignedTna'] : null;
        $this->rejectionReason = isset($data['rejectionReason']) ? (string)$data['rejectionReason'] : null;
    }

    #[Assert\Callback]
    public function validateDecisionConsistency(ExecutionContextInterface $context): void
    {
        if ($this->status === 'APPROVED' && $this->assignedTna === null) {
            $context->buildViolation('assignedTna is required when status is APPROVED.')
                ->atPath('assignedTna')
                ->addViolation();
        }

        if ($this->status === 'REJECTED' && $this->rejectionReason === null) {
            $context->buildViolation('rejectionReason is required when status is REJECTED.')
                ->atPath('rejectionReason')
                ->addViolation();
        }
    }
}
