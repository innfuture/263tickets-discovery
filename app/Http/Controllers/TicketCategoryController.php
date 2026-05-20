<?php

namespace App\Http\Controllers;

use App\Enums\TicketSaleStatus;
use App\Http\Requests\Tickets\StoreTicketCategoryRequest;
use App\Http\Requests\Tickets\UpdateTicketCategoryRequest;
use App\Models\Event;
use App\Models\Team;
use App\Models\TicketCategory;
use App\Services\ImageProcessingService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketCategoryController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function index(string $current_team, Event $event): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);

        $categories = $event->ticketCategories()
            ->with(['currencyPrices', 'discounts', 'promoCodes'])
            ->get()
            ->map(fn (TicketCategory $cat) => $this->categoryPayload($cat));

        return response()->json(['categories' => $categories]);
    }

    public function store(
        StoreTicketCategoryRequest $request,
        string $current_team,
        Event $event,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $this->authoriseEvent($current_team, $event);

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageService->processAndStore(
                $request->file('image'),
                'ticket-categories',
                ImageProcessingService::BANNER_SIZES,
            );
        }

        unset($data['image']);

        $this->ticketService->createCategory($event, $data);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Ticket category created.',
        ]);
    }

    public function update(
        UpdateTicketCategoryRequest $request,
        string $current_team,
        Event $event,
        TicketCategory $category,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($category->image_path) {
                $imageService->delete($category->image_path);
            }
            $data['image_path'] = $imageService->processAndStore(
                $request->file('image'),
                'ticket-categories',
                ImageProcessingService::BANNER_SIZES,
            );
        }

        unset($data['image']);

        $this->ticketService->updateCategory($category, $data);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Ticket category updated.',
        ]);
    }

    public function destroy(string $current_team, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $this->ticketService->deleteCategory($category);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Ticket category deleted.',
        ]);
    }

    public function generationStatus(string $current_team, Event $event, TicketCategory $category): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $category->refresh();

        return response()->json([
            'generation_status' => $category->generation_status?->value,
            'generation_progress' => $category->generation_progress,
        ]);
    }

    public function updateSaleStatus(Request $request, string $current_team, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $data = $request->validate([
            'sale_status' => ['required', 'string', 'in:active,paused,stopped'],
        ]);

        $category->update(['sale_status' => TicketSaleStatus::from($data['sale_status'])]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Sale status updated.',
        ]);
    }

    public function storeDiscount(Request $request, string $current_team, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $this->ticketService->createDiscount($category, $data);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Discount created.',
        ]);
    }

    public function storePromoCode(Request $request, string $current_team, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($category->event_id === $event->id, 404);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash'],
            'type' => ['required', 'string', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $this->ticketService->createPromoCode($category, $data);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Promo code created.',
        ]);
    }

    private function authoriseEvent(string $teamSlug, Event $event): void
    {
        $team = Team::where('slug', $teamSlug)->firstOrFail();
        abort_unless($event->organisation_id === $team->uuid, 403);
    }

    /** @return array<string, mixed> */
    private function categoryPayload(TicketCategory $cat): array
    {
        return [
            'id' => $cat->id,
            'uuid' => $cat->uuid,
            'name' => $cat->name,
            'description' => $cat->description,
            'image_url' => $cat->image_path
                ? Storage::url($cat->image_path)
                : null,
            'offline_quantity' => $cat->offline_quantity,
            'online_quantity' => $cat->online_quantity,
            'base_price' => $cat->base_price !== null
                ? (float) $cat->base_price
                : 0.0,
            'base_currency' => $cat->base_currency ?? 'USD',
            'min_per_order' => $cat->min_per_order,
            'max_per_order' => $cat->max_per_order,
            'is_visible' => (bool) $cat->is_visible,
            'sales_start_at' => $cat->sales_start_at?->toISOString(),
            'sales_end_at' => $cat->sales_end_at?->toISOString(),
            'generation_status' => $cat->generation_status?->value,
            'generation_progress' => $cat->generation_progress,
            'sale_status' => [
                'value' => $cat->sale_status->value,
                'label' => $cat->sale_status->label(),
            ],
            'admission_type' => $cat->admission_type
                ? ['value' => $cat->admission_type->value, 'label' => $cat->admission_type->label()]
                : null,
            'pass_type' => $cat->pass_type
                ? ['value' => $cat->pass_type->value, 'label' => $cat->pass_type->label()]
                : null,
            'sort_order' => $cat->sort_order,
            'currency_prices' => $cat->currencyPrices->map(fn ($p) => [
                'id' => $p->id,
                'currency' => $p->currency_code,
                'price' => (float) $p->price,
            ])->values(),
            'discounts' => $cat->discounts->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'value' => (float) $d->value,
                'max_uses' => $d->max_uses,
                'starts_at' => $d->starts_at?->toISOString(),
                'ends_at' => $d->ends_at?->toISOString(),
            ])->values(),
            'promo_codes' => $cat->promoCodes->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'type' => $p->type,
                'value' => (float) $p->value,
                'max_uses' => $p->max_uses,
                'starts_at' => $p->starts_at?->toISOString(),
                'ends_at' => $p->ends_at?->toISOString(),
            ])->values(),
        ];
    }
}
