<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultNotifier;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Service\CreditRequestApplicationNotifierInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestApplicationResultNotifierTest extends TestCase
{
    public function testApprovedApplicationSendsTheApprovedEmail(): void
    {
        $application = $this->application();
        $application->approve();

        $notifier = $this->createMock(CreditRequestApplicationNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyApproved')->with($application);
        $notifier->expects(self::never())->method('notifyRejected');

        (new CreditRequestApplicationResultNotifier($notifier))->execute($application);
    }

    public function testRejectedApplicationSendsTheRejectedEmail(): void
    {
        $application = $this->application();
        $application->reject('CAPACIDAD_PAGO_EXCEDIDA');

        $notifier = $this->createMock(CreditRequestApplicationNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyRejected')->with($application);
        $notifier->expects(self::never())->method('notifyApproved');

        (new CreditRequestApplicationResultNotifier($notifier))->execute($application);
    }

    public function testPendingApplicationSendsNoEmail(): void
    {
        $notifier = $this->createMock(CreditRequestApplicationNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyApproved');
        $notifier->expects(self::never())->method('notifyRejected');

        (new CreditRequestApplicationResultNotifier($notifier))->execute($this->application());
    }

    private function application(): CreditRequestApplication
    {
        return new CreditRequestApplication(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Ampliación planta',
            '1000000',
            12,
            new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@example.com', TaxStatus::Monotributo),
        );
    }
}
