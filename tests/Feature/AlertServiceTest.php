<?php

use App\Models\AuditEvent;
use App\Services\Security\AlertService;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->alertService = app(AlertService::class);
});

it('triggers alert when risk score exceeds threshold', function () {
    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->once();

    $event = AuditEvent::factory()->create(['risk_score' => 85]);

    $triggered = $this->alertService->evaluate($event);

    expect($triggered)->toBeTrue();
    expect($event->fresh()->alert_triggered)->toBeTrue();
});

it('does not trigger alert when risk score is below threshold', function () {
    $event = AuditEvent::factory()->create(['risk_score' => 30]);

    $triggered = $this->alertService->evaluate($event);

    expect($triggered)->toBeFalse();
    expect($event->fresh()->alert_triggered)->toBeFalse();
});

it('does not trigger alert at exactly the threshold boundary', function () {
    $threshold = config('security.risk.alert_threshold');
    $event = AuditEvent::factory()->create(['risk_score' => $threshold - 1]);

    $triggered = $this->alertService->evaluate($event);

    expect($triggered)->toBeFalse();
});

it('triggers alert at exactly the threshold', function () {
    Log::shouldReceive('channel')->with('security')->andReturnSelf();
    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->once();

    $threshold = config('security.risk.alert_threshold');
    $event = AuditEvent::factory()->create(['risk_score' => $threshold]);

    $triggered = $this->alertService->evaluate($event);

    expect($triggered)->toBeTrue();
});
