<?php

namespace App\Services\Security;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Evaluate whether an alert should be triggered and handle it.
     */
    public function evaluate(AuditEvent $event): bool
    {
        $threshold = config('security.risk.alert_threshold');

        if ($event->risk_score < $threshold) {
            return false;
        }

        $event->update(['alert_triggered' => true]);

        $this->dispatchAlert($event);

        return true;
    }

    private function dispatchAlert(AuditEvent $event): void
    {
        $alertData = [
            'alert_level' => $this->determineAlertLevel($event->risk_score),
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'source_service' => $event->source_service,
            'user_id' => $event->user_id,
            'risk_score' => $event->risk_score,
            'payload' => $event->payload,
            'created_at' => $event->created_at?->toIso8601String(),
        ];

        Log::channel('security')->warning('SECURITY ALERT: Suspicious activity detected', $alertData);

        Log::channel('security')->info('Alert dispatched', [
            'event_id' => $event->event_id,
            'alert_level' => $alertData['alert_level'],
            'risk_score' => $event->risk_score,
        ]);
    }

    private function determineAlertLevel(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 90 => 'critical',
            $riskScore >= 80 => 'high',
            $riskScore >= 70 => 'medium',
            default => 'low',
        };
    }
}
