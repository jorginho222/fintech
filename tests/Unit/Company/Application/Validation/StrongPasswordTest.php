<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\Validation;

use App\Company\Application\Validation\StrongPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class StrongPasswordTest extends TestCase
{
    public function testAPasswordMeetingEveryRuleIsAccepted(): void
    {
        self::assertSame([], $this->violations('Secret123!'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function weakPasswords(): iterable
    {
        yield 'too short' => ['Se1!', 'Este valor es demasiado corto. Debería tener 8 caracteres o más.'];
        yield 'no uppercase' => ['secret123!', 'La contraseña debe contener al menos una letra mayúscula.'];
        yield 'no number' => ['Secret!!x', 'La contraseña debe contener al menos un número.'];
        yield 'no symbol' => ['Secret123', 'La contraseña debe contener al menos un carácter especial.'];
    }

    #[DataProvider('weakPasswords')]
    public function testEachUnmetRuleIsReported(string $password, string $expectedMessage): void
    {
        self::assertSame([$expectedMessage], $this->violations($password));
    }

    public function testEveryUnmetRuleIsListedAtOnce(): void
    {
        self::assertCount(3, $this->violations('abcdefgh'));
    }

    /** @return string[] */
    private function violations(string $password): array
    {
        $messages = [];
        foreach ($this->validator()->validate($password, new StrongPassword()) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    /** Mirrors the app's validator, which translates built-in messages to Spanish. */
    private function validator(): ValidatorInterface
    {
        $translator = new Translator('es');
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource(
            'xlf',
            dirname((new \ReflectionClass(Validation::class))->getFileName()) . '/Resources/translations/validators.es.xlf',
            'es',
            'validators',
        );

        return Validation::createValidatorBuilder()
            ->setTranslator($translator)
            ->setTranslationDomain('validators')
            ->getValidator();
    }
}
