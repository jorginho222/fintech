<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Service\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private readonly PasswordHasherFactoryInterface $passwordHasherFactory) {}

    public function hash(string $plainPassword): string
    {
        return $this->passwordHasherFactory->getPasswordHasher(CompanyUser::class)->hash($plainPassword);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        return $this->passwordHasherFactory->getPasswordHasher(CompanyUser::class)->verify($hashedPassword, $plainPassword);
    }
}
