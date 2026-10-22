<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\Validation;

use App\Company\Application\Validation\StrongPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

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
        yield 'too short' => ['Se1!', 'This value is too short. It should have 8 characters or more.'];
        yield 'no uppercase' => ['secret123!', 'Password must contain at least one capital letter.'];
        yield 'no number' => ['Secret!!x', 'Password must contain at least one number.'];
        yield 'no symbol' => ['Secret123', 'Password must contain at least one special character.'];
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
        foreach (Validation::createValidator()->validate($password, new StrongPassword()) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
