<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', 'max:100'],
            'source_service' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'min_risk_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'alert_triggered' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
