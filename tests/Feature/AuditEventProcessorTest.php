<?php

use App\Models\AuditEvent;
use App\Services\Security\AuditEventProcessor;

beforeEach(function () {
    $this->processor = app(AuditEventProcessor::class);
});

it('processes a valid security event', function () {
    $eventData = [
        'event_id' => fake()->uuid(),
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'user_id' => 1,
        'entity_id' => '1234',
        'payload' => ['amount' => 500, 'currency' => 'USD'],
    ];

    $result = $this->processor->process($eventData);

    expect($result)
        ->not->toBeNull()
        ->event_id->toBe($eventData['event_id'])
        ->event_type->toBe('transaction.created')
        ->source_service->toBe('payment-service')
        ->user_id->toBe(1)
        ->entity_id->toBe('1234');

    $this->assertDatabaseHas('audit_events', [
        'event_id' => $eventData['event_id'],
        'event_type' => 'transaction.created',
    ]);
});

it('enforces idempotency by skipping duplicate event_ids', function () {
    $eventId = fake()->uuid();

    $eventData = [
        'event_id' => $eventId,
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'user_id' => 1,
        'payload' => ['amount' => 500],
    ];

    $first = $this->processor->process($eventData);
    $second = $this->processor->process($eventData);

    expect($first->id)->toBe($second->id);
    expect(AuditEvent::query()->where('event_id', $eventId)->count())->toBe(1);
});

it('rejects events without event_id', function () {
    $result = $this->processor->process([
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'payload' => ['amount' => 500],
    ]);

    expect($result)->toBeNull();
});

it('calculates and stores risk score', function () {
    $eventData = [
        'event_id' => fake()->uuid(),
        'event_type' => 'withdrawal.requested',
        'source_service' => 'payment-service',
        'user_id' => 1,
        'payload' => ['amount' => 50000],
    ];

    $result = $this->processor->process($eventData);

    expect($result->risk_score)->toBeGreaterThan(0);
});

it('handles null user_id and entity_id gracefully', function () {
    $eventData = [
        'event_id' => fake()->uuid(),
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'payload' => ['amount' => 100],
    ];

    $result = $this->processor->process($eventData);

    expect($result)
        ->not->toBeNull()
        ->user_id->toBeNull()
        ->entity_id->toBeNull();
});

it('triggers alert for high risk events', function () {
    // Create conditions for high risk
    $userId = 77;
    AuditEvent::factory(10)->create([
        'user_id' => $userId,
        'created_at' => now()->subMinute(),
    ]);

    $eventData = [
        'event_id' => fake()->uuid(),
        'event_type' => 'withdrawal.requested',
        'source_service' => 'payment-service',
        'user_id' => $userId,
        'payload' => ['amount' => 50000],
    ];

    $result = $this->processor->process($eventData);

    if ($result->risk_score >= config('security.risk.alert_threshold')) {
        expect($result->alert_triggered)->toBeTrue();
    }
});

it('stores empty payload as empty array', function () {
    $eventData = [
        'event_id' => fake()->uuid(),
        'event_type' => 'account.login',
        'source_service' => 'auth-service',
        'user_id' => 1,
        'payload' => [],
    ];

    $result = $this->processor->process($eventData);

    expect($result->payload)->toBeArray()->toBeEmpty();
});
