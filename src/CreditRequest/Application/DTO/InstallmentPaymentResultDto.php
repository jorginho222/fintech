<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstallmentPaymentResultDto
{
    public const string STATUS_APPROVED          = 'APPROVED';
    public const string STATUS_REJECTED          = 'REJECTED';
    public const string STATUS_PROCESSING_FAILED = 'PROCESSING_FAILED';

    private const int ERROR_MESSAGE_MAX_LENGTH = 1000;

    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [4])]
    public readonly string $installmentId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_PROCESSING_FAILED])]
    public readonly string $status;

    public readonly ?string $errorMessage;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->installmentId = (string) ($data['installmentId'] ?? '');
        $this->status        = (string) ($data['status'] ?? '');
        $this->errorMessage  = isset($data['errorMessage']) ? (string) $data['errorMessage'] : null;

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }

    #[Assert\Callback]
    public function validateErrorMessage(ExecutionContextInterface $context): void
    {
        if ($this->status !== self::STATUS_PROCESSING_FAILED) {
            return;
        }

        if ($this->errorMessage === null || trim($this->errorMessage) === '') {
            $context->buildViolation('El campo errorMessage es obligatorio cuando el estado es PROCESSING_FAILED.')
                ->atPath('errorMessage')
                ->addViolation();
        } elseif (mb_strlen($this->errorMessage) > self::ERROR_MESSAGE_MAX_LENGTH) {
            $context->buildViolation('El campo errorMessage no puede superar los 1000 caracteres.')
                ->atPath('errorMessage')
                ->addViolation();
        }
    }
}
