<?php

namespace App\Services\RabbitMQ;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;
use Illuminate\Support\Facades\Log;

class RabbitMQConnection
{
    private ?AMQPConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function getConnection(): AMQPConnection
    {
        if ($this->connection === null || ! $this->connection->isConnected()) {
            $this->connection = new AMQPConnection([
                'host' => config('security.rabbitmq.host', config('queue.connections.rabbitmq.hosts.0.host', '127.0.0.1')),
                'port' => config('security.rabbitmq.port', config('queue.connections.rabbitmq.hosts.0.port', 5672)),
                'login' => config('security.rabbitmq.user', config('queue.connections.rabbitmq.hosts.0.user', 'guest')),
                'password' => config('security.rabbitmq.password', config('queue.connections.rabbitmq.hosts.0.password', 'guest')),
                'vhost' => config('security.rabbitmq.vhost', config('queue.connections.rabbitmq.hosts.0.vhost', '/')),
                'read_timeout' => 30,
                'write_timeout' => 30,
                'connect_timeout' => 10,
                'heartbeat' => 15,
            ]);
            $this->connection->connect();
        }

        return $this->connection;
    }

    public function getChannel(): AMQPChannel
    {
        if ($this->channel === null || ! $this->channel->isConnected()) {
            $this->channel = new AMQPChannel($this->getConnection());
            $this->channel->setPrefetchCount(1);
        }

        return $this->channel;
    }

    public function declareExchange(string $name, string $type = AMQP_EX_TYPE_DIRECT): AMQPExchange
    {
        $exchange = new AMQPExchange($this->getChannel());
        $exchange->setName($name);
        $exchange->setType($type);
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->declareExchange();

        return $exchange;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function declareQueue(string $name, array $arguments = []): AMQPQueue
    {
        $queue = new AMQPQueue($this->getChannel());
        $queue->setName($name);
        $queue->setFlags(AMQP_DURABLE);

        foreach ($arguments as $key => $value) {
            $queue->setArgument($key, $value);
        }

        $queue->declareQueue();

        return $queue;
    }

    public function disconnect(): void
    {
        try {
            if ($this->channel?->isConnected()) {
                // Channel will be closed when connection disconnects
            }

            if ($this->connection?->isConnected()) {
                $this->connection->disconnect();
            }
        } catch (\Throwable $e) {
            Log::warning('RabbitMQ disconnect error: '.$e->getMessage());
        } finally {
            $this->channel = null;
            $this->connection = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
