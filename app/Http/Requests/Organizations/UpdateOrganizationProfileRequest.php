<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizerType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the Organization profile form. Public-facing fields are
 * length-bound to keep cards / OG tags rendering cleanly; address fields
 * mirror the event-address shape so the same UI primitives can be reused.
 *
 * The image uploads (logo / banner) are validated here but actually
 * processed by the controller via ImageProcessingService.
 */
class UpdateOrganizationProfileRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // Customer-facing trading name (e.g. "263tickets"). Falls
            // back to `name` (the registered entity) when not set.
            'brand_name' => ['nullable', 'string', 'min:2', 'max:120'],

            // Public profile
            // The 15-190 char floor/ceiling is the user-spec contract —
            // long enough to be informative on the organizer card, short
            // enough that no one tries to put a manifesto here.
            'tagline' => ['nullable', 'string', 'min:15', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'organizer_type' => ['nullable', Rule::enum(OrganizerType::class)],

            // Image uploads — actual storage happens in the controller.
            // SVG is permitted on the logo because brand logos are
            // commonly distributed as vector assets.
            'logo' => [
                'nullable',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
                'max:5120',
            ],
            'banner' => [
                'nullable',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],
            // Explicit-removal flags emitted by the in-image overlay
            // when the user clicks Remove on a persisted asset without
            // replacing it. Boolean coercion handles "1" / "true" /
            // checkbox-style values.
            'remove_logo' => ['nullable', 'boolean'],
            'remove_banner' => ['nullable', 'boolean'],

            // Contact
            'contact_email' => ['nullable', 'email', 'max:191'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'website_url' => ['nullable', 'url', 'max:2048'],

            // Social
            'twitter_url' => ['nullable', 'url', 'max:2048'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'tiktok_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],

            // Physical address (mirrors events.address_*).
            'address_line_1' => ['nullable', 'string', 'max:160'],
            'address_line_2' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Business attributes
            'tax_id' => ['nullable', 'string', 'max:64'],
            'default_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'default_timezone' => ['nullable', 'string', 'timezone'],
            'founded_year' => ['nullable', 'integer', 'min:1800', 'max:'.(int) date('Y')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->filled('country_code')
                ? strtoupper((string) $this->input('country_code'))
                : null,
            'default_currency' => $this->filled('default_currency')
                ? strtoupper((string) $this->input('default_currency'))
                : null,
        ]);
    }
}
