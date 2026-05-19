<?php

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:60'],
            'og_description' => ['nullable', 'string', 'max:160'],
            'og_image_path' => ['nullable', 'string', 'max:2048'],
            'twitter_card' => ['nullable', 'string', 'in:summary,summary_large_image,player,app'],
            'twitter_creator' => ['nullable', 'string', 'max:32', 'regex:/^@?[A-Za-z0-9_]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'meta_title' => $this->nullableString($this->input('meta_title')),
            'meta_description' => $this->nullableString($this->input('meta_description')),
            'canonical_url' => $this->nullableString($this->input('canonical_url')),
            'og_title' => $this->nullableString($this->input('og_title')),
            'og_description' => $this->nullableString($this->input('og_description')),
            'og_image_path' => $this->nullableString($this->input('og_image_path')),
            'twitter_card' => $this->nullableString($this->input('twitter_card')),
            'twitter_creator' => $this->normalizeHandle($this->input('twitter_creator')),
        ]);
    }

    private function nullableString(mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function normalizeHandle(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = ltrim(trim($value), '@');

        return '@'.$trimmed;
    }
}
