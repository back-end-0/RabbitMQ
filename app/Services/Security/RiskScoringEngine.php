<?php

namespace App\Services\Security;

use App\Models\AuditEvent;

class RiskScoringEngine
{
    /**
     * Calculate the risk score for a security event.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function calculate(array $eventData): int
    {
        $score = 0;
        $weights = config('security.risk.weights');
        $payload = $eventData['payload'] ?? [];

        $score += $this->scoreTransactionAmount($payload, $weights);
        $score += $this->scoreTimeOfDay($weights);
        $score += $this->scoreEventType($eventData['event_type'] ?? '', $weights);
        $score += $this->scoreRapidActivity($eventData, $weights);
        $score += $this->scoreFailedLogins($eventData, $weights);

        return min($score, 100);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $weights
     */
    private function scoreTransactionAmount(array $payload, array $weights): int
    {
        $amount = (float) ($payload['amount'] ?? 0);
        $threshold = config('security.risk.large_transaction_threshold');

        if ($amount >= $threshold) {
            return $weights['large_transaction'] ?? 0;
        }

        if ($amount >= $threshold * 0.5) {
            return (int) (($weights['large_transaction'] ?? 0) * 0.5);
        }

        return 0;
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function scoreTimeOfDay(array $weights): int
    {
        $hour = (int) now()->format('H');

        if ($hour >= 0 && $hour < 6) {
            return $weights['unusual_hour'] ?? 0;
        }

        return 0;
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function scoreEventType(string $eventType, array $weights): int
    {
        $highRiskEvents = [
            'account.password_changed' => $weights['password_change'] ?? 0,
            'user.role_changed' => $weights['role_change'] ?? 0,
            'api_key.generated' => $weights['api_key_generated'] ?? 0,
            'withdrawal.requested' => $weights['high_value_withdrawal'] ?? 0,
        ];

        return $highRiskEvents[$eventType] ?? 0;
    }

    /**
     * Check for rapid successive activity from the same user.
     *
     * @param  array<string, mixed>  $eventData
     * @param  array<string, int>  $weights
     */
    private function scoreRapidActivity(array $eventData, array $weights): int
    {
        $userId = $eventData['user_id'] ?? null;

        if ($userId === null) {
            return 0;
        }

        $windowMinutes = config('security.risk.rapid_transaction_window_minutes');
        $maxCount = config('security.risk.rapid_transaction_count');

        $recentCount = AuditEvent::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentCount >= $maxCount) {
            return $weights['rapid_transactions'] ?? 0;
        }

        return 0;
    }

    /**
     * Check for multiple failed login attempts.
     *
     * @param  array<string, mixed>  $eventData
     * @param  array<string, int>  $weights
     */
    private function scoreFailedLogins(array $eventData, array $weights): int
    {
        if (($eventData['event_type'] ?? '') !== 'account.login_failed') {
            return 0;
        }

        $userId = $eventData['user_id'] ?? null;

        if ($userId === null) {
            return 0;
        }

        $failedCount = AuditEvent::query()
            ->where('user_id', $userId)
            ->where('event_type', 'account.login_failed')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($failedCount >= 3) {
            return $weights['multiple_failed_logins'] ?? 0;
        }

        return 0;
    }
}
