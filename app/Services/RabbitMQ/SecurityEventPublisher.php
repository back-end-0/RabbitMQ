<?php

namespace App\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityEventPublisher
{
    public function __construct(
        private RabbitMQConnection $connection
    ) {}

    /**
     * Publish a security event to the security exchange.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $eventType,
        string $sourceService,
        ?int $userId = null,
        ?string $entityId = null,
        array $payload = [],
        ?string $eventId = null,
    ): string {
        $eventId = $eventId ?? Str::uuid()->toString();
        $exchange = config('security.rabbitmq.exchange');
        $routingKey = config('security.rabbitmq.queue');

        $message = json_encode([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'source_service' => $sourceService,
            'user_id' => $userId,
            'entity_id' => $entityId,
            'payload' => $payload,
            'published_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $amqpExchange = $this->connection->declareExchange($exchange);

        $amqpExchange->publish(
            $message,
            $routingKey,
            AMQP_NOPARAM,
            [
                'delivery_mode' => 2, // Persistent
                'content_type' => 'application/json',
                'message_id' => $eventId,
                'timestamp' => time(),
                'app_id' => $sourceService,
            ]
        );

        Log::channel('security')->info('Security event published', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'source_service' => $sourceService,
        ]);

        return $eventId;
    }
}
