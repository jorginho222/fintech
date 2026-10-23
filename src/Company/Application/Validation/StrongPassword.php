<?php

declare(strict_types=1);

namespace App\Company\Application\Validation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Password policy for every password a user sets: 8+ characters, an uppercase letter, a number
 * and a symbol. Each rule reports its own violation so the response lists every unmet rule.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class StrongPassword extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: 8, max: 4096),
            new Assert\Regex(pattern: '/[A-Z]/', message: 'La contraseña debe contener al menos una letra mayúscula.'),
            new Assert\Regex(pattern: '/\d/', message: 'La contraseña debe contener al menos un número.'),
            new Assert\Regex(pattern: '/[^A-Za-z\d]/', message: 'La contraseña debe contener al menos un carácter especial.'),
        ];
    }
}
