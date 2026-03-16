<?php

namespace App\Enums;

enum EventType: string
{
    case TransactionCreated = 'transaction.created';
    case TransactionUpdated = 'transaction.updated';
    case AccountLogin = 'account.login';
    case AccountLoginFailed = 'account.login_failed';
    case PasswordChanged = 'account.password_changed';
    case PaymentProcessed = 'payment.processed';
    case PaymentRefunded = 'payment.refunded';
    case UserRoleChanged = 'user.role_changed';
    case ApiKeyGenerated = 'api_key.generated';
    case WithdrawalRequested = 'withdrawal.requested';
    case TransferInitiated = 'transfer.initiated';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
