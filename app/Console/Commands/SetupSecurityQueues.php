<?php

namespace App\Console\Commands;

use App\Services\RabbitMQ\RabbitMQConnection;
use Illuminate\Console\Command;

class SetupSecurityQueues extends Command
{
    protected $signature = 'security:setup-queues';

    protected $description = 'Set up RabbitMQ exchanges, queues, and dead letter queues for the security audit system';

    public function handle(RabbitMQConnection $connection): int
    {
        $this->info('Setting up security RabbitMQ infrastructure...');

        try {
            $this->setupDeadLetterInfrastructure($connection);
            $this->setupRetryInfrastructure($connection);
            $this->setupMainInfrastructure($connection);

            $this->newLine();
            $this->info('Security RabbitMQ infrastructure setup complete!');

            $connection->disconnect();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to set up RabbitMQ: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function setupDeadLetterInfrastructure(RabbitMQConnection $connection): void
    {
        $dlxName = config('security.rabbitmq.dead_letter_exchange');
        $dlqName = config('security.rabbitmq.dead_letter_queue');

        $this->task('Creating dead letter exchange', function () use ($connection, $dlxName) {
            $connection->declareExchange($dlxName);
        });

        $this->task('Creating dead letter queue', function () use ($connection, $dlxName, $dlqName) {
            $queue = $connection->declareQueue($dlqName);
            $queue->bind($dlxName, $dlqName);
        });
    }

    private function setupRetryInfrastructure(RabbitMQConnection $connection): void
    {
        $retryExchange = config('security.rabbitmq.retry_exchange');
        $retryQueue = config('security.rabbitmq.retry_queue');
        $mainExchange = config('security.rabbitmq.exchange');
        $mainQueue = config('security.rabbitmq.queue');
        $retryDelay = config('security.rabbitmq.retry_delay');

        $this->task('Creating retry exchange', function () use ($connection, $retryExchange) {
            $connection->declareExchange($retryExchange);
        });

        $this->task('Creating retry queue (TTL: '.$retryDelay.'ms)', function () use ($connection, $retryExchange, $retryQueue, $mainExchange, $mainQueue, $retryDelay) {
            $queue = $connection->declareQueue($retryQueue, [
                'x-message-ttl' => $retryDelay,
                'x-dead-letter-exchange' => $mainExchange,
                'x-dead-letter-routing-key' => $mainQueue,
            ]);
            $queue->bind($retryExchange, $retryQueue);
        });
    }

    private function setupMainInfrastructure(RabbitMQConnection $connection): void
    {
        $exchangeName = config('security.rabbitmq.exchange');
        $queueName = config('security.rabbitmq.queue');
        $dlxName = config('security.rabbitmq.dead_letter_exchange');

        $this->task('Creating main security exchange', function () use ($connection, $exchangeName) {
            $connection->declareExchange($exchangeName);
        });

        $this->task('Creating main security queue', function () use ($connection, $exchangeName, $queueName, $dlxName) {
            $queue = $connection->declareQueue($queueName, [
                'x-dead-letter-exchange' => $dlxName,
                'x-dead-letter-routing-key' => config('security.rabbitmq.dead_letter_queue'),
            ]);
            $queue->bind($exchangeName, $queueName);
        });
    }
}
