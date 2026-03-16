<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishSecurityEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::in(EventType::values())],
            'source_service' => ['required', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'entity_id' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'event_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_type.in' => 'The event type must be one of: '.implode(', ', EventType::values()),
            'payload.required' => 'The event payload is required.',
            'payload.array' => 'The event payload must be a JSON object.',
        ];
    }
}
