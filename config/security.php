<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Security Queue Configuration
    |--------------------------------------------------------------------------
    */

    'rabbitmq' => [
        'exchange' => env('SECURITY_EXCHANGE', 'security.events'),
        'queue' => env('SECURITY_QUEUE', 'security.audit'),
        'dead_letter_exchange' => env('SECURITY_DLX', 'security.dlx'),
        'dead_letter_queue' => env('SECURITY_DLQ', 'security.dead_letter'),
        'retry_exchange' => env('SECURITY_RETRY_EXCHANGE', 'security.retry'),
        'retry_queue' => env('SECURITY_RETRY_QUEUE', 'security.retry'),
        'retry_delay' => (int) env('SECURITY_RETRY_DELAY', 30000),
        'max_retries' => (int) env('SECURITY_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Configuration
    |--------------------------------------------------------------------------
    */

    'risk' => [
        'alert_threshold' => (int) env('RISK_ALERT_THRESHOLD', 70),

        'weights' => [
            'large_transaction' => 30,
            'unusual_hour' => 15,
            'multiple_failed_logins' => 25,
            'new_device' => 10,
            'foreign_ip' => 20,
            'rapid_transactions' => 25,
            'password_change' => 10,
            'role_change' => 15,
            'api_key_generated' => 10,
            'high_value_withdrawal' => 30,
        ],

        'large_transaction_threshold' => (float) env('LARGE_TRANSACTION_THRESHOLD', 10000.00),
        'rapid_transaction_window_minutes' => (int) env('RAPID_TRANSACTION_WINDOW', 5),
        'rapid_transaction_count' => (int) env('RAPID_TRANSACTION_COUNT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Configuration
    |--------------------------------------------------------------------------
    */

    'alerts' => [
        'channels' => ['log', 'database'],
        'log_channel' => env('SECURITY_ALERT_LOG_CHANNEL', 'security'),
    ],

];
