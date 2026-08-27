<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientDiagnosticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(['app', 'web'])],
            'category' => ['required', Rule::in(['authentication', 'javascript', 'network', 'lifecycle'])],
            'code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'stage' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'message' => ['required', 'string', 'max:500'],
            'path' => ['nullable', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'android_version' => ['nullable', 'string', 'max:50'],
            'device' => ['nullable', 'string', 'max:120'],
        ];
    }
}
