<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\RefundPolicy;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Refund policy CRUD. Named policies can later be attached to ticket
 * categories; the attendee sees the policy text at checkout.
 */
class RefundPolicyController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'finance.process-refund');

        $policies = RefundPolicy::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (RefundPolicy $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'cutoff_hours' => $p->cutoff_hours,
                'prorated' => $p->prorated,
                'restocking_fee_percent' => $p->restocking_fee_percent,
                'is_default' => $p->is_default,
            ]);

        return Inertia::render('settings/billing/refunds', [
            'policies' => $policies,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Refund policies', 'href' => '/settings/billing/refunds'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'finance.process-refund');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cutoff_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'prorated' => ['required', 'boolean'],
            'restocking_fee_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'is_default' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($org, $data) {
            if ($data['is_default']) {
                RefundPolicy::where('organization_id', $org->id)->update(['is_default' => false]);
            }
            RefundPolicy::create([...$data, 'organization_id' => $org->id]);
        });

        $audit->record('refund_policy.created', $org, $request->user(), after: ['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Policy created.')]);

        return back();
    }

    public function update(Request $request, RefundPolicy $policy, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'finance.process-refund');
        abort_if($policy->organization_id !== $org->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cutoff_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'prorated' => ['required', 'boolean'],
            'restocking_fee_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'is_default' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($org, $policy, $data) {
            if ($data['is_default']) {
                RefundPolicy::where('organization_id', $org->id)
                    ->where('id', '!=', $policy->id)
                    ->update(['is_default' => false]);
            }
            $policy->update($data);
        });

        $audit->record('refund_policy.updated', $org, $request->user(), resourceId: (string) $policy->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Policy saved.')]);

        return back();
    }

    public function destroy(Request $request, RefundPolicy $policy, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'finance.process-refund');
        abort_if($policy->organization_id !== $org->id, 403);

        $audit->record('refund_policy.deleted', $org, $request->user(), before: ['name' => $policy->name]);
        $policy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Policy deleted.')]);

        return back();
    }
}
