<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TicketCategory;
use App\Models\TicketDiscount;
use App\Models\TicketPromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-wide library of discounts + promo codes. Both already exist
 * per-category from the ticket pages — this page surfaces the
 * combined catalogue, with filters by event, code, and active state.
 *
 * Create/update routes attach a new code to a chosen ticket_category
 * inline so organizers don't have to navigate into the event first.
 */
class DiscountsController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('ticket_category.manage-discounts'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $categoryIds = TicketCategory::query()
            ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
            ->pluck('id');

        $discounts = TicketDiscount::query()
            ->whereIn('ticket_category_id', $categoryIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (TicketDiscount $d) {
                $category = TicketCategory::with('event:id,slug,name')->find($d->ticket_category_id);

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'discount_type' => $d->discount_type,
                    'value' => (string) $d->value,
                    'min_quantity' => (int) $d->min_quantity,
                    'max_uses' => $d->max_uses,
                    'uses_count' => (int) $d->uses_count,
                    'starts_at' => $d->starts_at?->toIso8601String(),
                    'expires_at' => $d->expires_at?->toIso8601String(),
                    'is_active' => (bool) $d->is_active,
                    'category' => $category?->name,
                    'event_name' => $category?->event?->name,
                    'event_slug' => $category?->event?->slug,
                ];
            })
            ->all();

        $promoCodes = TicketPromoCode::query()
            ->whereIn('ticket_category_id', $categoryIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (TicketPromoCode $p) {
                $category = TicketCategory::with('event:id,slug,name')->find($p->ticket_category_id);

                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'discount_type' => $p->discount_type,
                    'value' => (string) $p->value,
                    'max_uses' => $p->max_uses,
                    'uses_count' => (int) $p->uses_count,
                    'expires_at' => $p->expires_at?->toIso8601String(),
                    'is_active' => (bool) $p->is_active,
                    'category' => $category?->name,
                    'event_name' => $category?->event?->name,
                    'event_slug' => $category?->event?->slug,
                ];
            })
            ->all();

        // Category dropdown for the create form.
        $categories = TicketCategory::query()
            ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
            ->with('event:id,name')
            ->orderByDesc('id')
            ->get(['id', 'name', 'event_id'])
            ->map(fn (TicketCategory $c) => [
                'value' => $c->id,
                'label' => ($c->event?->name ?? 'Unknown').' · '.$c->name,
            ])
            ->all();

        return Inertia::render('discounts/index', [
            'discounts' => $discounts,
            'promo_codes' => $promoCodes,
            'categories' => $categories,
            'permissions' => [
                'can_manage_promo' => $request->user()->can('ticket_category.manage-promo-codes'),
            ],
            'breadcrumbs' => [
                ['title' => 'Discounts', 'href' => "/{$current_organization}/discounts"],
            ],
        ]);
    }

    public function storeDiscount(Request $request, string $current_organization): RedirectResponse
    {
        abort_unless($request->user()->can('ticket_category.manage-discounts'), 403);

        $data = $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->assertCategoryBelongsToOrg($request, (int) $data['ticket_category_id']);

        TicketDiscount::create([
            'ticket_category_id' => $data['ticket_category_id'],
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'min_quantity' => $data['min_quantity'] ?? 1,
            'max_uses' => $data['max_uses'] ?? null,
            'uses_count' => 0,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Discount added.']);

        return back();
    }

    public function storePromo(Request $request, string $current_organization): RedirectResponse
    {
        abort_unless($request->user()->can('ticket_category.manage-promo-codes'), 403);

        $data = $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'code' => ['required', 'string', 'max:40', 'unique:ticket_promo_codes,code'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->assertCategoryBelongsToOrg($request, (int) $data['ticket_category_id']);

        TicketPromoCode::create([
            'ticket_category_id' => $data['ticket_category_id'],
            'code' => strtoupper($data['code']),
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'max_uses' => $data['max_uses'] ?? null,
            'uses_count' => 0,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Promo code added.']);

        return back();
    }

    public function toggleDiscount(Request $request, string $current_organization, TicketDiscount $discount): RedirectResponse
    {
        abort_unless($request->user()->can('ticket_category.manage-discounts'), 403);
        $this->assertCategoryBelongsToOrg($request, (int) $discount->ticket_category_id);

        $discount->update(['is_active' => ! $discount->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Discount '.($discount->is_active ? 'enabled' : 'paused').'.']);

        return back();
    }

    public function togglePromo(Request $request, string $current_organization, TicketPromoCode $promo): RedirectResponse
    {
        abort_unless($request->user()->can('ticket_category.manage-promo-codes'), 403);
        $this->assertCategoryBelongsToOrg($request, (int) $promo->ticket_category_id);

        $promo->update(['is_active' => ! $promo->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Promo code '.($promo->is_active ? 'enabled' : 'paused').'.']);

        return back();
    }

    /**
     * Guard cross-org tampering: the category must belong to an event
     * in the viewer's active organisation.
     */
    protected function assertCategoryBelongsToOrg(Request $request, int $categoryId): void
    {
        $orgId = $request->user()->currentOrganization?->id;
        $exists = TicketCategory::query()
            ->where('id', $categoryId)
            ->whereIn('event_id', Event::where('organisation_id', $orgId)->select('id'))
            ->exists();

        abort_unless($exists, 403);
    }
}
