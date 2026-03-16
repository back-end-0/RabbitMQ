<?php

use App\Enums\EventType;

it('has all required event types', function () {
    $values = EventType::values();

    expect($values)
        ->toContain('transaction.created')
        ->toContain('transaction.updated')
        ->toContain('account.login')
        ->toContain('account.login_failed')
        ->toContain('account.password_changed')
        ->toContain('payment.processed')
        ->toContain('payment.refunded')
        ->toContain('user.role_changed')
        ->toContain('api_key.generated')
        ->toContain('withdrawal.requested')
        ->toContain('transfer.initiated');
});

it('resolves enum from value', function () {
    $enum = EventType::from('transaction.created');

    expect($enum)->toBe(EventType::TransactionCreated);
});

it('returns values as string array', function () {
    $values = EventType::values();

    expect($values)->toBeArray();
    expect(count($values))->toBe(count(EventType::cases()));
});
