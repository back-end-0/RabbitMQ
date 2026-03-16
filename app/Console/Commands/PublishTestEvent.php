<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Services\RabbitMQ\SecurityEventPublisher;
use Illuminate\Console\Command;

class PublishTestEvent extends Command
{
    protected $signature = 'security:publish-test
                            {--type=transaction.created : Event type}
                            {--service=payment-service : Source service name}
                            {--user=1 : User ID}
                            {--amount=100 : Transaction amount}
                            {--count=1 : Number of events to publish}';

    protected $description = 'Publish test security events to RabbitMQ for development and testing';

    public function handle(SecurityEventPublisher $publisher): int
    {
        $count = (int) $this->option('count');
        $eventType = $this->option('type');
        $service = $this->option('service');
        $userId = (int) $this->option('user');
        $amount = (float) $this->option('amount');

        $validTypes = EventType::values();
        if (! in_array($eventType, $validTypes)) {
            $this->error("Invalid event type: {$eventType}");
            $this->info('Valid types: '.implode(', ', $validTypes));

            return self::FAILURE;
        }

        $this->info("Publishing {$count} test event(s)...");

        for ($i = 0; $i < $count; $i++) {
            $eventId = $publisher->publish(
                eventType: $eventType,
                sourceService: $service,
                userId: $userId,
                entityId: (string) rand(1000, 9999),
                payload: [
                    'amount' => $amount,
                    'currency' => 'USD',
                    'ip_address' => '192.168.1.'.rand(1, 254),
                    'user_agent' => 'TestPublisher/1.0',
                    'description' => "Test event #{$i}",
                ],
            );

            $this->components->twoColumnDetail(
                "<fg=green>Published</> {$eventId}",
                "<fg=gray>{$eventType}</>"
            );
        }

        $this->newLine();
        $this->info("Successfully published {$count} event(s).");

        return self::SUCCESS;
    }
}
