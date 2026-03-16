<?php

use App\Models\AuditEvent;

describe('POST /api/v1/security/events', function () {
    it('publishes a valid security event', function () {
        $this->mock(\App\Services\RabbitMQ\SecurityEventPublisher::class)
            ->shouldReceive('publish')
            ->once()
            ->andReturn('test-event-id');

        $response = $this->postJson('/api/v1/security/events', [
            'event_type' => 'transaction.created',
            'source_service' => 'payment-service',
            'user_id' => 1,
            'entity_id' => '1234',
            'payload' => ['amount' => 500, 'currency' => 'USD'],
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Security event published successfully.',
                'event_id' => 'test-event-id',
            ]);
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/security/events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type', 'source_service', 'payload']);
    });

    it('validates event_type against allowed values', function () {
        $this->postJson('/api/v1/security/events', [
            'event_type' => 'invalid.type',
            'source_service' => 'test-service',
            'payload' => ['test' => true],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);
    });

    it('accepts optional event_id as uuid', function () {
        $this->mock(\App\Services\RabbitMQ\SecurityEventPublisher::class)
            ->shouldReceive('publish')
            ->once()
            ->andReturn('custom-id');

        $this->postJson('/api/v1/security/events', [
            'event_type' => 'account.login',
            'source_service' => 'auth-service',
            'payload' => ['ip' => '127.0.0.1'],
            'event_id' => fake()->uuid(),
        ])->assertStatus(202);
    });

    it('rejects invalid uuid for event_id', function () {
        $this->postJson('/api/v1/security/events', [
            'event_type' => 'account.login',
            'source_service' => 'auth-service',
            'payload' => ['ip' => '127.0.0.1'],
            'event_id' => 'not-a-uuid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_id']);
    });
});

describe('GET /api/v1/security/audit', function () {
    it('lists audit events with pagination', function () {
        AuditEvent::factory(30)->create();

        $response = $this->getJson('/api/v1/security/audit');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'event_id', 'event_type', 'source_service',
                        'user_id', 'entity_id', 'payload', 'risk_score',
                        'alert_triggered', 'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        expect($response->json('meta.per_page'))->toBe(25);
    });

    it('filters by event_type', function () {
        AuditEvent::factory()->create(['event_type' => 'account.login']);
        AuditEvent::factory()->create(['event_type' => 'transaction.created']);

        $response = $this->getJson('/api/v1/security/audit?event_type=account.login');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.event_type'))->toBe('account.login');
    });

    it('filters by source_service', function () {
        AuditEvent::factory()->create(['source_service' => 'auth-service']);
        AuditEvent::factory()->create(['source_service' => 'payment-service']);

        $response = $this->getJson('/api/v1/security/audit?source_service=auth-service');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('filters by minimum risk score', function () {
        AuditEvent::factory()->create(['risk_score' => 20]);
        AuditEvent::factory()->create(['risk_score' => 80]);
        AuditEvent::factory()->create(['risk_score' => 95]);

        $response = $this->getJson('/api/v1/security/audit?min_risk_score=70');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('filters by user_id', function () {
        AuditEvent::factory()->create(['user_id' => 1]);
        AuditEvent::factory()->create(['user_id' => 2]);

        $response = $this->getJson('/api/v1/security/audit?user_id=1');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('filters by alert_triggered', function () {
        AuditEvent::factory()->create(['alert_triggered' => true]);
        AuditEvent::factory()->create(['alert_triggered' => false]);

        $response = $this->getJson('/api/v1/security/audit?alert_triggered=1');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('supports custom per_page', function () {
        AuditEvent::factory(15)->create();

        $response = $this->getJson('/api/v1/security/audit?per_page=5');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(5);
        expect($response->json('meta.per_page'))->toBe(5);
    });
});

describe('GET /api/v1/security/audit/{eventId}', function () {
    it('shows a single audit event', function () {
        $event = AuditEvent::factory()->create();

        $response = $this->getJson("/api/v1/security/audit/{$event->event_id}");

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'event_id' => $event->event_id,
                    'event_type' => $event->event_type,
                ],
            ]);
    });

    it('returns 404 for non-existent event', function () {
        $this->getJson('/api/v1/security/audit/non-existent-id')
            ->assertNotFound();
    });
});

describe('GET /api/v1/security/audit/stats', function () {
    it('returns audit statistics', function () {
        AuditEvent::factory(10)->create(['risk_score' => 20]);
        AuditEvent::factory(3)->create(['risk_score' => 85, 'alert_triggered' => true]);

        $response = $this->getJson('/api/v1/security/audit/stats');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'total_events',
                'high_risk_events',
                'alerts_triggered',
                'average_risk_score',
                'events_by_type',
                'events_by_service',
            ]);

        expect($response->json('total_events'))->toBe(13);
        expect($response->json('high_risk_events'))->toBe(3);
        expect($response->json('alerts_triggered'))->toBe(3);
    });

    it('returns zero stats when no events exist', function () {
        $response = $this->getJson('/api/v1/security/audit/stats');

        $response->assertSuccessful();
        expect($response->json('total_events'))->toBe(0);
        expect($response->json('average_risk_score'))->toBe(0);
    });
});
