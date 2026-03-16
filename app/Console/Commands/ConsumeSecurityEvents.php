<?php

namespace App\Console\Commands;

use AMQPEnvelope;
use AMQPQueue;
use App\Services\RabbitMQ\RabbitMQConnection;
use App\Services\Security\AuditEventProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumeSecurityEvents extends Command
{
    protected $signature = 'security:consume
                            {--max-messages=0 : Maximum messages to process (0 = unlimited)}
                            {--timeout=0 : Consumer timeout in seconds (0 = unlimited)}';

    protected $description = 'Consume security events from RabbitMQ and process them through the audit pipeline';

    private bool $shouldStop = false;

    public function handle(
        RabbitMQConnection $connection,
        AuditEventProcessor $processor
    ): int {
        $maxMessages = (int) $this->option('max-messages');
        $timeout = (int) $this->option('timeout');
        $processed = 0;
        $startTime = time();

        $this->info('Starting security event consumer...');
        $this->info('Queue: '.config('security.rabbitmq.queue'));
        $this->info('Press Ctrl+C to stop.');
        $this->newLine();

        $this->registerSignalHandlers();

        try {
            $queueName = config('security.rabbitmq.queue');
            $retryExchange = config('security.rabbitmq.retry_exchange');
            $retryQueue = config('security.rabbitmq.retry_queue');
            $maxRetries = config('security.rabbitmq.max_retries');

            $queue = $connection->declareQueue($queueName, [
                'x-dead-letter-exchange' => config('security.rabbitmq.dead_letter_exchange'),
                'x-dead-letter-routing-key' => config('security.rabbitmq.dead_letter_queue'),
            ]);
            $queue->bind(config('security.rabbitmq.exchange'), $queueName);

            $retryExchangeObj = $connection->declareExchange($retryExchange);

            while (! $this->shouldStop) {
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info('Timeout reached. Stopping consumer.');

                    break;
                }

                if ($maxMessages > 0 && $processed >= $maxMessages) {
                    $this->info('Max messages reached. Stopping consumer.');

                    break;
                }

                $envelope = $queue->get();

                if (! $envelope instanceof AMQPEnvelope) {
                    usleep(100_000); // 100ms wait

                    continue;
                }

                $this->processMessage(
                    $envelope,
                    $queue,
                    $processor,
                    $retryExchangeObj,
                    $retryQueue,
                    $maxRetries
                );

                $processed++;
            }

            $this->newLine();
            $this->info("Consumer stopped. Processed {$processed} messages.");

            $connection->disconnect();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Consumer error: '.$e->getMessage());
            Log::channel('security')->error('Consumer fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    private function processMessage(
        AMQPEnvelope $envelope,
        AMQPQueue $queue,
        AuditEventProcessor $processor,
        \AMQPExchange $retryExchange,
        string $retryQueue,
        int $maxRetries
    ): void {
        $body = $envelope->getBody();
        $deliveryTag = $envelope->getDeliveryTag();

        try {
            /** @var array<string, mixed> $eventData */
            $eventData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $result = $processor->process($eventData);

            if ($result !== null) {
                $queue->ack($deliveryTag);
                $this->components->twoColumnDetail(
                    "<fg=green>ACK</> {$eventData['event_id']}",
                    "<fg=gray>{$eventData['event_type']}</> risk:<fg=yellow>{$result->risk_score}</>"
                );
            } else {
                $queue->nack($deliveryTag);
                $this->components->twoColumnDetail(
                    '<fg=red>NACK</> Message rejected',
                    'Invalid event data'
                );
            }
        } catch (\JsonException $e) {
            $queue->nack($deliveryTag);
            Log::channel('security')->error('Invalid JSON in message', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->handleFailedMessage($envelope, $queue, $retryExchange, $retryQueue, $maxRetries, $e);
        }
    }

    private function handleFailedMessage(
        AMQPEnvelope $envelope,
        AMQPQueue $queue,
        \AMQPExchange $retryExchange,
        string $retryQueue,
        int $maxRetries,
        \Throwable $error
    ): void {
        $deliveryTag = $envelope->getDeliveryTag();
        $headers = $envelope->getHeaders();
        $retryCount = (int) ($headers['x-retry-count'] ?? 0);

        if ($retryCount < $maxRetries) {
            // Republish to retry queue with incremented retry count
            $retryExchange->publish(
                $envelope->getBody(),
                $retryQueue,
                AMQP_NOPARAM,
                [
                    'delivery_mode' => 2,
                    'content_type' => 'application/json',
                    'headers' => [
                        'x-retry-count' => $retryCount + 1,
                        'x-last-error' => $error->getMessage(),
                        'x-original-routing-key' => config('security.rabbitmq.queue'),
                    ],
                ]
            );

            $queue->ack($deliveryTag);

            $this->components->twoColumnDetail(
                '<fg=yellow>RETRY</> Attempt '.($retryCount + 1)."/{$maxRetries}",
                substr($error->getMessage(), 0, 60)
            );

            Log::channel('security')->warning('Message scheduled for retry', [
                'retry_count' => $retryCount + 1,
                'error' => $error->getMessage(),
            ]);
        } else {
            // Max retries exceeded, nack to send to DLQ
            $queue->nack($deliveryTag);

            $this->components->twoColumnDetail(
                '<fg=red>DLQ</> Max retries exceeded',
                substr($error->getMessage(), 0, 60)
            );

            Log::channel('security')->error('Message sent to dead letter queue', [
                'retry_count' => $retryCount,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }
    }
}
