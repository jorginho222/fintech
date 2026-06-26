<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Domain\Model\TaxStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyCreateDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [4])]
    public readonly string $id;

    #[Assert\NotBlank]
    public readonly string $socialReason;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{11}$/')]
    public readonly string $cuit;

    #[Assert\NotBlank]
    #[Assert\Email(mode: 'html5')]
    public readonly string $email;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [TaxStatus::class, 'values'])]
    public readonly string $taxStatus;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->id           = (string) ($data['id'] ?? '');
        $this->socialReason = (string) ($data['socialReason'] ?? '');
        $this->cuit         = (string) ($data['cuit'] ?? '');
        $this->email        = (string) ($data['email'] ?? '');
        $this->taxStatus    = (string) ($data['taxStatus'] ?? '');

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
