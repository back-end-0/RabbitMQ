<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditEventIndexRequest;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditEventController extends Controller
{
    /**
     * List audit events with filtering and pagination.
     */
    public function index(AuditEventIndexRequest $request): AnonymousResourceCollection
    {
        $query = AuditEvent::query()->latest('created_at');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->validated('event_type'));
        }

        if ($request->filled('source_service')) {
            $query->where('source_service', $request->validated('source_service'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->validated('user_id'));
        }

        if ($request->filled('min_risk_score')) {
            $query->highRisk((int) $request->validated('min_risk_score'));
        }

        if ($request->has('alert_triggered')) {
            $query->where('alert_triggered', $request->boolean('alert_triggered'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->validated('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->validated('to'));
        }

        $perPage = $request->integer('per_page', 25);

        return AuditEventResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single audit event by event_id.
     */
    public function show(string $eventId): AuditEventResource|JsonResponse
    {
        $event = AuditEvent::query()->where('event_id', $eventId)->first();

        if (! $event) {
            return response()->json(['message' => 'Audit event not found.'], 404);
        }

        return new AuditEventResource($event);
    }

    /**
     * Get summary statistics for audit events.
     */
    public function stats(): JsonResponse
    {
        $totalEvents = AuditEvent::query()->count();
        $highRiskEvents = AuditEvent::query()->highRisk()->count();
        $alertsTriggered = AuditEvent::query()->where('alert_triggered', true)->count();

        $eventsByType = AuditEvent::query()
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');

        $eventsByService = AuditEvent::query()
            ->selectRaw('source_service, COUNT(*) as count')
            ->groupBy('source_service')
            ->pluck('count', 'source_service');

        $averageRiskScore = AuditEvent::query()->avg('risk_score') ?? 0;

        return response()->json([
            'total_events' => $totalEvents,
            'high_risk_events' => $highRiskEvents,
            'alerts_triggered' => $alertsTriggered,
            'average_risk_score' => round((float) $averageRiskScore, 2),
            'events_by_type' => $eventsByType,
            'events_by_service' => $eventsByService,
        ]);
    }
}
