<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'event_type',
        'source_service',
        'user_id',
        'entity_id',
        'payload',
        'risk_score',
        'alert_triggered',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'risk_score' => 'integer',
            'alert_triggered' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope: filter by high-risk events.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeHighRisk($query, int $threshold = 70)
    {
        return $query->where('risk_score', '>=', $threshold);
    }

    /**
     * Scope: filter by source service.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeFromService($query, string $service)
    {
        return $query->where('source_service', $service);
    }
}
