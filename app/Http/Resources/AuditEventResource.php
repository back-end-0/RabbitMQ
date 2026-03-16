<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'source_service' => $this->source_service,
            'user_id' => $this->user_id,
            'entity_id' => $this->entity_id,
            'payload' => $this->payload,
            'risk_score' => $this->risk_score,
            'alert_triggered' => $this->alert_triggered,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
