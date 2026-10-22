<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\PasswordChangeDto;
use App\Company\Application\UseCase\CurrentUserProvider;
use App\Company\Application\UseCase\PasswordChanger;
use App\Company\Domain\Exception\InvalidCurrentPasswordException;
use App\Company\Domain\Exception\NewPasswordMustDifferException;
use App\Company\Domain\Exception\PasswordChangeNotAllowedException;
use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Model\User;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthenticatedUserIdProviderInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class PasswordChangerTest extends TestCase
{
    private const string INITIAL_PASSWORD = 'Initial123!';
    private const string NEW_PASSWORD     = 'Changed456?';

    public function testTheInitialPasswordIsReplacedAndTheFlagCleared(): void
    {
        $member = $this->member();

        $userRepository = $this->repositoryReturning($member);
        $userRepository->expects(self::once())->method('save')->with($member);

        $this->changer($member, $userRepository)->execute($this->dto(self::INITIAL_PASSWORD, self::NEW_PASSWORD));

        self::assertSame('hashed:' . self::NEW_PASSWORD, $member->getHashedPassword());
        self::assertFalse($member->mustChangePassword());
    }

    public function testAWrongCurrentPasswordIsRejected(): void
    {
        $this->assertRejected($this->member(), $this->dto('Wrong123!', self::NEW_PASSWORD), InvalidCurrentPasswordException::class);
    }

    public function testReusingTheCurrentPasswordIsRejected(): void
    {
        $this->assertRejected($this->member(), $this->dto(self::INITIAL_PASSWORD, self::INITIAL_PASSWORD), NewPasswordMustDifferException::class);
    }

    public function testAMemberThatAlreadyChangedItsPasswordCannotUseThisFlowAgain(): void
    {
        $member = $this->member();
        $member->changeInitialPassword('hashed:' . self::NEW_PASSWORD);

        $this->assertRejected($member, $this->dto(self::NEW_PASSWORD, 'Another789#'), PasswordChangeNotAllowedException::class);
    }

    public function testTheAdminCannotUseThisFlow(): void
    {
        $admin = AdminUser::forCompany('cccccccc-cccc-4ccc-cccc-cccccccccccc', $this->company(), 'hashed:' . self::INITIAL_PASSWORD);

        $this->assertRejected($admin, $this->dto(self::INITIAL_PASSWORD, self::NEW_PASSWORD), PasswordChangeNotAllowedException::class);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    private function assertRejected(User $user, PasswordChangeDto $dto, string $exception): void
    {
        $userRepository = $this->repositoryReturning($user);
        $userRepository->expects(self::never())->method('save');

        $this->expectException($exception);

        $this->changer($user, $userRepository)->execute($dto);
    }

    private function repositoryReturning(User $user): UserRepositoryInterface
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with($user->getId())->willReturn($user);

        return $userRepository;
    }

    private function changer(User $authenticatedUser, UserRepositoryInterface $userRepository): PasswordChanger
    {
        $userIdProvider = $this->createMock(AuthenticatedUserIdProviderInterface::class);
        $userIdProvider->method('getUserId')->willReturn($authenticatedUser->getId());

        // A fake hasher: "hashed:<plain>" so verification is a plain comparison.
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturnCallback(static fn (string $plain): string => 'hashed:' . $plain);
        $hasher->method('verify')->willReturnCallback(static fn (string $hash, string $plain): bool => $hash === 'hashed:' . $plain);

        return new PasswordChanger(new CurrentUserProvider($userRepository, $userIdProvider), $userRepository, $hasher);
    }

    private function member(): MemberUser
    {
        return new MemberUser('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', $this->company(), '27333444555', 'hashed:' . self::INITIAL_PASSWORD, 'Ana', []);
    }

    private function company(): Company
    {
        return new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@empresa.com', TaxStatus::Monotributo);
    }

    private function dto(string $currentPassword, string $newPassword): PasswordChangeDto
    {
        $request = new Request([], [], [], [], [], [], json_encode(
            ['currentPassword' => $currentPassword, 'newPassword' => $newPassword],
            JSON_THROW_ON_ERROR,
        ));

        return new PasswordChangeDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
