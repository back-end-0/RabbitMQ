<?php

use App\Models\AuditEvent;
use App\Services\Security\RiskScoringEngine;

beforeEach(function () {
    $this->engine = app(RiskScoringEngine::class);
});

it('returns zero for a low-risk event', function () {
    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'account.login',
        'source_service' => 'auth-service',
        'user_id' => 1,
        'payload' => [
            'amount' => 50,
            'ip_address' => '192.168.1.1',
        ],
    ]);

    expect($score)->toBeInt()->toBeLessThan(70);
});

it('scores large transactions', function () {
    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'user_id' => 1,
        'payload' => [
            'amount' => 25000,
        ],
    ]);

    expect($score)->toBeGreaterThanOrEqual(30);
});

it('scores medium transactions at half weight', function () {
    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'user_id' => 1,
        'payload' => [
            'amount' => 6000,
        ],
    ]);

    expect($score)->toBeGreaterThanOrEqual(15);
});

it('scores high-risk event types', function () {
    $highRiskTypes = [
        'account.password_changed',
        'user.role_changed',
        'api_key.generated',
        'withdrawal.requested',
    ];

    foreach ($highRiskTypes as $type) {
        $score = $this->engine->calculate([
            'event_id' => fake()->uuid(),
            'event_type' => $type,
            'source_service' => 'auth-service',
            'user_id' => 1,
            'payload' => [],
        ]);

        expect($score)->toBeGreaterThan(0, "Expected non-zero score for {$type}");
    }
});

it('scores rapid activity from same user', function () {
    $userId = 42;

    // Create several recent events for the same user
    AuditEvent::factory(6)->create([
        'user_id' => $userId,
        'created_at' => now()->subMinute(),
    ]);

    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'transaction.created',
        'source_service' => 'payment-service',
        'user_id' => $userId,
        'payload' => ['amount' => 100],
    ]);

    expect($score)->toBeGreaterThanOrEqual(25);
});

it('scores failed login attempts', function () {
    $userId = 99;

    AuditEvent::factory(4)->create([
        'user_id' => $userId,
        'event_type' => 'account.login_failed',
        'created_at' => now()->subMinutes(5),
    ]);

    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'account.login_failed',
        'source_service' => 'auth-service',
        'user_id' => $userId,
        'payload' => [],
    ]);

    expect($score)->toBeGreaterThanOrEqual(25);
});

it('caps risk score at 100', function () {
    $userId = 50;

    // Create conditions that would push score over 100
    AuditEvent::factory(10)->create([
        'user_id' => $userId,
        'event_type' => 'account.login_failed',
        'created_at' => now()->subMinute(),
    ]);

    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'withdrawal.requested',
        'source_service' => 'payment-service',
        'user_id' => $userId,
        'payload' => ['amount' => 50000],
    ]);

    expect($score)->toBeLessThanOrEqual(100);
});

it('returns zero score when no risk factors present', function () {
    $score = $this->engine->calculate([
        'event_id' => fake()->uuid(),
        'event_type' => 'account.login',
        'source_service' => 'auth-service',
        'payload' => ['amount' => 10],
    ]);

    expect($score)->toBeLessThanOrEqual(15); // Only time-of-day could contribute
});
