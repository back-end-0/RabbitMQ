<?php

use App\Models\AuditEvent;

it('creates an audit event with factory', function () {
    $event = AuditEvent::factory()->create();

    expect($event)
        ->event_id->not->toBeEmpty()
        ->event_type->not->toBeEmpty()
        ->source_service->not->toBeEmpty()
        ->payload->toBeArray();
});

it('casts payload to array', function () {
    $event = AuditEvent::factory()->create([
        'payload' => ['amount' => 500, 'currency' => 'USD'],
    ]);

    $event->refresh();

    expect($event->payload)
        ->toBeArray()
        ->toHaveKey('amount', 500)
        ->toHaveKey('currency', 'USD');
});

it('casts risk_score to integer', function () {
    $event = AuditEvent::factory()->create(['risk_score' => 85]);

    expect($event->risk_score)->toBeInt()->toBe(85);
});

it('casts alert_triggered to boolean', function () {
    $event = AuditEvent::factory()->create(['alert_triggered' => true]);

    expect($event->alert_triggered)->toBeBool()->toBeTrue();
});

it('enforces unique event_id', function () {
    $event = AuditEvent::factory()->create();

    AuditEvent::factory()->create(['event_id' => $event->event_id]);
})->throws(\Illuminate\Database\QueryException::class);

it('scopes high risk events', function () {
    AuditEvent::factory()->create(['risk_score' => 30]);
    AuditEvent::factory()->create(['risk_score' => 80]);
    AuditEvent::factory()->create(['risk_score' => 95]);

    $highRisk = AuditEvent::query()->highRisk()->get();

    expect($highRisk)->toHaveCount(2);
    $highRisk->each(fn ($event) => expect($event->risk_score)->toBeGreaterThanOrEqual(70));
});

it('scopes high risk events with custom threshold', function () {
    AuditEvent::factory()->create(['risk_score' => 50]);
    AuditEvent::factory()->create(['risk_score' => 80]);
    AuditEvent::factory()->create(['risk_score' => 95]);

    expect(AuditEvent::query()->highRisk(90)->count())->toBe(1);
});

it('scopes events by source service', function () {
    AuditEvent::factory()->create(['source_service' => 'payment-service']);
    AuditEvent::factory()->create(['source_service' => 'auth-service']);
    AuditEvent::factory()->create(['source_service' => 'payment-service']);

    expect(AuditEvent::query()->fromService('payment-service')->count())->toBe(2);
});

it('creates high risk state from factory', function () {
    $event = AuditEvent::factory()->highRisk()->create();

    expect($event)
        ->risk_score->toBeGreaterThanOrEqual(70)
        ->alert_triggered->toBeTrue();
});

it('creates large transaction state from factory', function () {
    $event = AuditEvent::factory()->largeTransaction()->create();

    expect($event->event_type)->toBe('transaction.created');
    expect($event->payload['amount'])->toBeGreaterThanOrEqual(10000);
});
