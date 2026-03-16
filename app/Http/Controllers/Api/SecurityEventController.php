<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishSecurityEventRequest;
use App\Services\RabbitMQ\SecurityEventPublisher;
use Illuminate\Http\JsonResponse;

class SecurityEventController extends Controller
{
    /**
     * Publish a security event to the message broker.
     */
    public function publish(
        PublishSecurityEventRequest $request,
        SecurityEventPublisher $publisher
    ): JsonResponse {
        $validated = $request->validated();

        $eventId = $publisher->publish(
            eventType: $validated['event_type'],
            sourceService: $validated['source_service'],
            userId: $validated['user_id'] ?? null,
            entityId: $validated['entity_id'] ?? null,
            payload: $validated['payload'],
            eventId: $validated['event_id'] ?? null,
        );

        return response()->json([
            'message' => 'Security event published successfully.',
            'event_id' => $eventId,
        ], 202);
    }
}
