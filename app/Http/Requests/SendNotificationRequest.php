<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real la hace el middleware AuthenticateTenant.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['text', 'template'])],

            // Requerido cuando type = text
            'text' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],

            // Requeridos cuando type = template
            'template' => ['required_if:type,template', 'nullable', 'string'],
            'language' => ['nullable', 'string', 'max:10'],
            'params' => ['nullable', 'array'],
            'params.*' => ['string'],
        ];
    }
}
