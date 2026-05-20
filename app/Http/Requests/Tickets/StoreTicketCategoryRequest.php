<?php

namespace App\Http\Requests\Tickets;

use App\Enums\AdmissionType;
use App\Enums\PassType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:3072'],

            'admission_type' => ['required', Rule::enum(AdmissionType::class)],
            'pass_type' => ['required', Rule::enum(PassType::class)],

            'offline_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'online_quantity' => ['required', 'integer', 'min:0', 'max:500000'],

            'base_price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'base_currency' => ['required', 'string', 'size:3'],

            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],

            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],

            'currency_prices' => ['nullable', 'array', 'max:10'],
            'currency_prices.*.currency_code' => ['required', 'string', 'size:3'],
            'currency_prices.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $offline = (int) $this->input('offline_quantity', 0);
            $online = (int) $this->input('online_quantity', 0);

            if ($offline === 0 && $online === 0) {
                $v->errors()->add('offline_quantity', 'At least one of Offline or Online quantity must be greater than zero.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => filter_var($this->input('is_visible', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
