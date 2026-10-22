<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

/**
 * Fixed catalogue of resource accesses a member user can be granted.
 *
 * Values are persisted in each member's permission list, so they are stable identifiers:
 * never rename one. Removing a case requires a hand-written migration that strips the value
 * from stored lists, otherwise members holding it can no longer be loaded.
 */
enum Permission: string
{
    case CreditRequestApplicationList = 'credit_request_application.list';
    case CreditRequestApplicationCreate = 'credit_request_application.create';
    case CreditRequestConfirm = 'credit_request.confirm';
    case CreditRequestView = 'credit_request.view';
    case CreditRequestList = 'credit_request.list';
    case InstallmentPay = 'installment.pay';
    case PaymentCalendarView = 'payment_calendar.view';
    case TotalDebtView = 'total_debt.view';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The resource group the permission belongs to, used to present the catalogue grouped.
     */
    public function resource(): string
    {
        return match ($this) {
            self::CreditRequestApplicationList,
            self::CreditRequestApplicationCreate,
            self::CreditRequestConfirm => 'credit_request_application',
            self::CreditRequestView,
            self::CreditRequestList,
            self::InstallmentPay => 'credit_requests',
            self::PaymentCalendarView => 'payment_calendar',
            self::TotalDebtView => 'total_debt',
        };
    }
}
