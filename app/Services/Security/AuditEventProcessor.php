<?php

namespace App\Services\Security;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Log;

class AuditEventProcessor
{
    public function __construct(
        private RiskScoringEngine $riskEngine,
        private AlertService $alertService
    ) {}

    /**
     * Process an incoming security event with idempotency protection.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function process(array $eventData): ?AuditEvent
    {
        $eventId = $eventData['event_id'] ?? null;

        if ($eventId === null) {
            Log::channel('security')->error('Event rejected: missing event_id', $eventData);

            return null;
        }

        // Idempotency check
        $existing = AuditEvent::query()->where('event_id', $eventId)->first();

        if ($existing) {
            Log::channel('security')->info('Duplicate event skipped', ['event_id' => $eventId]);

            return $existing;
        }

        // Calculate risk score
        $riskScore = $this->riskEngine->calculate($eventData);

        // Store in database
        $auditEvent = AuditEvent::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventData['event_type'],
            'source_service' => $eventData['source_service'],
            'user_id' => $eventData['user_id'] ?? null,
            'entity_id' => $eventData['entity_id'] ?? null,
            'payload' => $eventData['payload'] ?? [],
            'risk_score' => $riskScore,
        ]);

        Log::channel('security')->info('Audit event stored', [
            'event_id' => $eventId,
            'event_type' => $eventData['event_type'],
            'risk_score' => $riskScore,
        ]);

        // Evaluate alerts
        $this->alertService->evaluate($auditEvent);

        return $auditEvent;
    }
}
