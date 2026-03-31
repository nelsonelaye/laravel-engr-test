<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insurer_id' => ['required', 'exists:insurers,id'],
            'provider_name' => ['required', 'string', 'min:2', 'max:255'],
            'encounter_date' => ['required', 'date', 'before_or_equal:today'],
            'specialty' => ['required', 'string', 'in:Cardiology,Orthopedics,Neurology,Pediatrics,General Practice'],
            'priority_level' => ['required', 'integer', 'min:1', 'max:5'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'min:1', 'max:255'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one claim item is required.',
            'items.min' => 'At least one claim item is required.',
            'encounter_date.before_or_equal' => 'Encounter date cannot be in the future.',
        ];
    }
}
