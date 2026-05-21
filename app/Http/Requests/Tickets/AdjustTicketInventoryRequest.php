<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an organizer request to nudge an offline ticket category's
 * inventory up or down. The downstream service applies the safety guards
 * (can't decrease below sold/scanned), but these rules catch the obvious
 * client mistakes (zero delta, > 100k swing).
 */
class AdjustTicketInventoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Positive = mint more, negative = void existing. Excluding
            // zero up front so the service doesn't have to.
            'delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:280'],
        ];
    }

    public function messages(): array
    {
        return [
            'delta.not_in' => 'Adjustment delta must be non-zero.',
            'delta.between' => 'Adjustment must be between -100,000 and +100,000 tickets per batch.',
        ];
    }
}
