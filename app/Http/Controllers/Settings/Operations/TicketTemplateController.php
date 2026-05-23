<?php

namespace App\Http\Controllers\Settings\Operations;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PDF + Apple Wallet ticket-template designer. The actual rendering
 * happens in the print pipeline; this page persists user choices.
 */
class TicketTemplateController extends SettingsController
{
    private const DEFAULTS = [
        'pdf_layout' => 'standard',
        'wallet_template' => 'minimal',
        'logo_placement' => 'header',
        'language_fallback' => 'en',
        'terms_footer' => '',
        'show_seat_info' => true,
        'show_qr_label' => true,
    ];

    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'ticket_category.update');
        $settings = OrganizationSetting::for($org);
        $template = array_replace(self::DEFAULTS, (array) $settings->get('ticket_template', []));

        return Inertia::render('settings/operations/ticket-templates', [
            'template' => $template,
            'options' => [
                'pdf_layouts' => ['standard', 'compact', 'festival', 'corporate'],
                'wallet_templates' => ['minimal', 'gradient', 'photo-bg'],
                'logo_placements' => ['header', 'footer', 'watermark', 'none'],
                'languages' => ['en', 'es', 'fr', 'de', 'pt', 'ja'],
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Ticket templates', 'href' => '/settings/operations/ticket-templates'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'ticket_category.update');

        $data = $request->validate([
            'pdf_layout' => ['required', 'in:standard,compact,festival,corporate'],
            'wallet_template' => ['required', 'in:minimal,gradient,photo-bg'],
            'logo_placement' => ['required', 'in:header,footer,watermark,none'],
            'language_fallback' => ['required', 'in:en,es,fr,de,pt,ja'],
            'terms_footer' => ['nullable', 'string', 'max:500'],
            'show_seat_info' => ['required', 'boolean'],
            'show_qr_label' => ['required', 'boolean'],
        ]);

        OrganizationSetting::for($org)->merge('ticket_template', $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket templates saved.')]);

        return back();
    }
}
