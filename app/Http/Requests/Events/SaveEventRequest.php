<?php

namespace App\Http\Requests\Events;

use App\Enums\EventVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:126'],
            'short_description' => ['nullable', 'string', 'max:280'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'doors_open_at' => ['nullable', 'date'],
            'timezone' => ['required', 'string', 'timezone'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'minimum_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'parking_info' => ['nullable', 'string', 'max:5000'],
            'age_requirement_details' => ['nullable', 'string', 'max:5000'],
            'venue_name' => ['nullable', 'string', 'max:120'],
            'address_line_1' => ['nullable', 'string', 'max:160'],
            'address_line_2' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_online' => ['sometimes', 'boolean'],
            'online_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['nullable', 'integer', 'exists:event_categories,id'],
            'refund_policy' => ['nullable', 'string', 'max:65535'],
            'terms' => ['nullable', 'string', 'max:65535'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'banner_image' => [
                'nullable',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],

            // Nested: lineup
            'lineup' => ['nullable', 'array'],
            'lineup.*.id' => ['nullable', 'integer'],
            'lineup.*.name' => ['required_with:lineup', 'string', 'max:120'],
            'lineup.*.role' => ['nullable', 'string', 'max:60'],
            'lineup.*.bio' => ['nullable', 'string', 'max:2000'],
            'lineup.*.image_path' => ['nullable', 'string', 'max:2048'],
            'lineup.*.social_url' => ['nullable', 'url', 'max:2048'],
            'lineup.*.is_headliner' => ['nullable', 'boolean'],

            // Nested: agenda
            'agenda' => ['nullable', 'array'],
            'agenda.*.id' => ['nullable', 'integer'],
            'agenda.*.starts_at' => ['required_with:agenda', 'date'],
            'agenda.*.ends_at' => ['nullable', 'date'],
            'agenda.*.title' => ['required_with:agenda', 'string', 'max:160'],
            'agenda.*.description' => ['nullable', 'string', 'max:2000'],
            'agenda.*.host_name' => ['nullable', 'string', 'max:120'],
            'agenda.*.host_role' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->filled('country_code')
                ? strtoupper((string) $this->input('country_code'))
                : null,
            'capacity' => $this->normalizeNullableString($this->input('capacity')),
            'minimum_age' => $this->normalizeNullableString($this->input('minimum_age')),
            'latitude' => $this->normalizeNullableString($this->input('latitude')),
            'longitude' => $this->normalizeNullableString($this->input('longitude')),
            'sales_start_at' => $this->normalizeNullableString($this->input('sales_start_at')),
            'sales_end_at' => $this->normalizeNullableString($this->input('sales_end_at')),
            'doors_open_at' => $this->normalizeNullableString($this->input('doors_open_at')),
            'category_id' => $this->normalizeNullableString($this->input('category_id')),
            'online_url' => $this->normalizeNullableString($this->input('online_url')),
            'contact_email' => $this->normalizeNullableString($this->input('contact_email')),
            'contact_phone' => $this->normalizeNullableString($this->input('contact_phone')),
            'venue_name' => $this->normalizeNullableString($this->input('venue_name')),
            'address_line_1' => $this->normalizeNullableString($this->input('address_line_1')),
            'address_line_2' => $this->normalizeNullableString($this->input('address_line_2')),
            'city' => $this->normalizeNullableString($this->input('city')),
            'region' => $this->normalizeNullableString($this->input('region')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'refund_policy' => $this->normalizeNullableString($this->input('refund_policy')),
            'terms' => $this->normalizeNullableString($this->input('terms')),
            'parking_info' => $this->normalizeNullableString($this->input('parking_info')),
            'age_requirement_details' => $this->normalizeNullableString($this->input('age_requirement_details')),
            'short_description' => $this->normalizeNullableString($this->input('short_description')),
            'description' => $this->normalizeNullableString($this->input('description')),
        ]);
    }

    private function normalizeNullableString(mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }
}
