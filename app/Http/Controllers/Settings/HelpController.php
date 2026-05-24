<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Help & support contact form. Two surfaces:
 *
 *   GET  /settings/help    static FAQ + topic list (in Inertia page)
 *   POST /settings/help    contact form submission
 *
 * The contact form delivers to `SUPPORT_EMAIL`. When the env var is
 * unset the message is written to the application log with the same
 * payload, so developers running locally still see what the request
 * would have sent without needing mail credentials.
 */
class HelpController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('settings/help', [
            'support_email' => config('mail.support_email', env('SUPPORT_EMAIL')),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $org = $user?->currentOrganization;
        $supportEmail = (string) (config('mail.support_email') ?? env('SUPPORT_EMAIL', ''));

        $context = [
            'subject' => $data['subject'],
            'message' => $data['message'],
            'from' => $user?->email,
            'user_id' => $user?->id,
            'org_slug' => $org?->slug,
            'org_uuid' => $org?->uuid,
            'submitted_at' => now()->toIso8601String(),
        ];

        if ($supportEmail !== '') {
            try {
                Mail::raw(
                    sprintf(
                        "From: %s\nOrg: %s\nSubmitted at: %s\n\n%s",
                        $context['from'] ?? 'anonymous',
                        $context['org_slug'] ?? 'none',
                        $context['submitted_at'],
                        $data['message'],
                    ),
                    function ($mail) use ($supportEmail, $data, $user) {
                        $mail->to($supportEmail)
                            ->subject('[support] '.$data['subject'])
                            ->replyTo($user?->email ?? $supportEmail, $user?->name);
                    },
                );

                Log::info('support.contact.sent', $context + ['delivery' => 'mail']);
            } catch (\Throwable $e) {
                Log::error('support.contact.failed', $context + ['error' => $e->getMessage()]);

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Could not deliver right now. Your message was logged for follow-up.',
                ]);

                return back();
            }
        } else {
            // Dev / unconfigured path — log so the developer sees the
            // payload that *would* have been sent.
            Log::info('support.contact.queued (no SUPPORT_EMAIL configured)', $context);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Thanks — your message is on its way to the support team.',
        ]);

        return back();
    }
}
