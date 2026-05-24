<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\Storefront\Passes\WalletPassService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a downloadable wallet pass for one ticket. Auth is via the
 * signed URL pattern — controller is reachable only through links
 * minted by `OrderConfirmationMail` / the receipt page.
 *
 *   GET /api/v1/public/orders/{reference}/items/{item}/pass?provider=apple|google|pdf
 */
class WalletPassController extends Controller
{
    public function __construct(protected WalletPassService $passes) {}

    public function show(Request $request, string $reference, int $itemId): Response
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'signature_invalid'], 403);
        }

        $item = OrderItem::query()
            ->with('order.organization', 'order.event')
            ->whereHas('order', fn ($q) => $q->where('reference', $reference))
            ->find($itemId);

        if (! $item) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $provider = (string) $request->query('provider', 'pdf');
        $pass = $this->passes->build($item, $provider);

        return response($pass['bytes'], 200, [
            'Content-Type' => $pass['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$pass['filename'].'"',
            'X-Pass-Provider' => $pass['provider'],
        ]);
    }
}
