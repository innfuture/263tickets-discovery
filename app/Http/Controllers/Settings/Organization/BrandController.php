<?php

namespace App\Http\Controllers\Settings\Organization;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Brand kit settings — logo variants, colors, fonts, email theming.
 *
 * Logo variants (light/dark/email) and palette/font choices persist
 * into the org's JSON settings bag under the `brand` namespace.
 * Uploads go to `public` disk under `organizations/{id}/brand/`.
 */
class BrandController extends SettingsController
{
    private const DEFAULTS = [
        'primary_color' => '#0f172a',
        'accent_color' => '#22c55e',
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
        'email_footer' => '',
        'pdf_header_text' => '',
        'logo_light_path' => null,
        'logo_dark_path' => null,
        'logo_email_path' => null,
    ];

    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-brand');
        $settings = OrganizationSetting::for($org);

        $brand = array_replace(self::DEFAULTS, (array) $settings->get('brand', []));
        $brand['logo_light_url'] = $brand['logo_light_path'] ? Storage::url($brand['logo_light_path']) : null;
        $brand['logo_dark_url'] = $brand['logo_dark_path'] ? Storage::url($brand['logo_dark_path']) : null;
        $brand['logo_email_url'] = $brand['logo_email_path'] ? Storage::url($brand['logo_email_path']) : null;

        return Inertia::render('settings/organization/brand', [
            'brand' => $brand,
            'fontOptions' => ['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Source Sans 3'],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Brand kit', 'href' => '/settings/organization/brand'],
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-brand');

        $data = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'heading_font' => ['required', 'string', 'max:64'],
            'body_font' => ['required', 'string', 'max:64'],
            'email_footer' => ['nullable', 'string', 'max:1000'],
            'pdf_header_text' => ['nullable', 'string', 'max:200'],
        ]);

        $settings = OrganizationSetting::for($org);
        $before = (array) $settings->get('brand', []);

        $settings->merge('brand', $data);

        $audit->record(
            action: 'organization.brand.updated',
            organization: $org,
            user: $request->user(),
            resourceType: 'organization',
            resourceId: (string) $org->id,
            before: $before,
            after: $data,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand kit saved.')]);

        return back();
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-brand');

        $request->validate([
            'variant' => ['required', 'in:light,dark,email'],
            'logo' => ['required', 'image', 'max:4096'],
        ]);

        $variant = $request->input('variant');
        $path = $request->file('logo')->store(
            "organizations/{$org->id}/brand",
            'public',
        );

        OrganizationSetting::for($org)->merge('brand', [
            "logo_{$variant}_path" => $path,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo uploaded.')]);

        return back();
    }

    public function deleteLogo(Request $request, string $variant): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-brand');
        abort_unless(in_array($variant, ['light', 'dark', 'email'], true), 422);

        $settings = OrganizationSetting::for($org);
        $key = "logo_{$variant}_path";
        if ($path = $settings->get("brand.{$key}")) {
            Storage::disk('public')->delete($path);
            $settings->set("brand.{$key}", null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return back();
    }
}
