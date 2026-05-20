<?php

namespace App\Http\Requests\Tickets;

use App\Enums\AdmissionType;
use App\Enums\PassType;
use App\Enums\TicketSaleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:3072'],

            'admission_type' => ['sometimes', Rule::enum(AdmissionType::class)],
            'pass_type' => ['sometimes', Rule::enum(PassType::class)],

            'offline_quantity' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'online_quantity' => ['sometimes', 'integer', 'min:0', 'max:500000'],

            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'base_currency' => ['sometimes', 'string', 'size:3'],

            'sale_status' => ['sometimes', Rule::enum(TicketSaleStatus::class)],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],

            'is_visible' => ['sometimes', 'boolean'],
            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'currency_prices' => ['nullable', 'array', 'max:10'],
            'currency_prices.*.currency_code' => ['required', 'string', 'size:3'],
            'currency_prices.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_visible')) {
            $this->merge([
                'is_visible' => filter_var($this->input('is_visible'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
