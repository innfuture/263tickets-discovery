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
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'robots_directive' => ['nullable', 'string', 'in:index,follow,index,nofollow,noindex,follow,noindex,nofollow'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:60'],
            'og_description' => ['nullable', 'string', 'max:160'],
            'og_image_path' => ['nullable', 'string', 'max:2048'],
            'og_type' => ['nullable', 'string', 'max:32'],
            'og_locale' => ['nullable', 'string', 'max:10'],
            'og_site_name' => ['nullable', 'string', 'max:100'],
            'twitter_card' => ['nullable', 'string', 'in:summary,summary_large_image,player,app'],
            'twitter_creator' => ['nullable', 'string', 'max:32', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'twitter_title' => ['nullable', 'string', 'max:70'],
            'twitter_description' => ['nullable', 'string', 'max:200'],
            'twitter_image' => ['nullable', 'string', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'meta_title', 'meta_description', 'seo_keywords', 'robots_directive',
            'canonical_url', 'og_title', 'og_description', 'og_image_path',
            'og_type', 'og_locale', 'og_site_name',
            'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image',
        ];

        $merged = [];
        foreach ($nullableFields as $field) {
            if ($this->has($field)) {
                $merged[$field] = $this->nullableString($this->input($field));
            }
        }

        if ($this->has('twitter_creator')) {
            $merged['twitter_creator'] = $this->normalizeHandle($this->input('twitter_creator'));
        }

        $this->merge($merged);
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
