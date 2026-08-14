<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use App\Company\Domain\Model\TaxStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyRegistrationDto
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

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    #[Assert\Regex(pattern: '/[A-Z]/', message: 'Password must contain at least one capital letter.')]
    #[Assert\Regex(pattern: '/[^A-Za-z\d]/', message: 'Password must contain at least one special character.')]
    public readonly string $password;

    public function __construct(Request $request, ValidatorInterface $validator)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->id           = (string) ($data['id'] ?? '');
        $this->socialReason = (string) ($data['socialReason'] ?? '');
        $this->cuit         = (string) ($data['cuit'] ?? '');
        $this->email        = (string) ($data['email'] ?? '');
        $this->taxStatus    = (string) ($data['taxStatus'] ?? '');
        $this->password     = (string) ($data['password'] ?? '');

        $violations = $validator->validate($this);
        if (count($violations) > 0) {
            throw new ValidationFailedException($this, $violations);
        }
    }
}
