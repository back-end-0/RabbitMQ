<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Str::uuid()->toString(),
            'event_type' => fake()->randomElement([
                'transaction.created',
                'transaction.updated',
                'account.login',
                'account.password_changed',
                'payment.processed',
                'payment.refunded',
                'user.role_changed',
                'api_key.generated',
                'withdrawal.requested',
                'transfer.initiated',
            ]),
            'source_service' => fake()->randomElement([
                'payment-service',
                'auth-service',
                'account-service',
                'transfer-service',
                'notification-service',
            ]),
            'user_id' => fake()->numberBetween(1, 100),
            'entity_id' => (string) fake()->numberBetween(1000, 9999),
            'payload' => [
                'amount' => fake()->randomFloat(2, 10, 50000),
                'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
            'risk_score' => 0,
            'alert_triggered' => false,
        ];
    }

    /**
     * State: high-risk event.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => fake()->numberBetween(70, 100),
            'alert_triggered' => true,
        ]);
    }

    /**
     * State: suspicious large transaction.
     */
    public function largeTransaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'transaction.created',
            'payload' => [
                'amount' => fake()->randomFloat(2, 10000, 100000),
                'currency' => 'USD',
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
        ]);
    }
}
