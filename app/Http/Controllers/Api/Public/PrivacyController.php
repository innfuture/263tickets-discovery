<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Storefront\Privacy\BuyerDataEraser;
use App\Services\Storefront\Privacy\BuyerDataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GDPR / CCPA buyer endpoints.
 *
 *   POST /api/v1/public/privacy/export   { email, order_reference }
 *   POST /api/v1/public/privacy/erase    { email, order_reference }
 *
 * Both require proving control of the email by supplying a known
 * order reference that matches. This keeps the surface unauthenticated
 * but stops drive-by enumeration / mass-erasure.
 */
class PrivacyController extends Controller
{
    public function __construct(
        protected BuyerDataExporter $exporter,
        protected BuyerDataEraser $eraser,
    ) {}

    public function export(Request $request): JsonResponse
    {
        $validated = $this->validateProofOfOwnership($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        return response()->json([
            'data' => $this->exporter->export($validated['email']),
        ]);
    }

    public function erase(Request $request): JsonResponse
    {
        $validated = $this->validateProofOfOwnership($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        return response()->json([
            'data' => $this->eraser->erase($validated['email']),
        ]);
    }

    /**
     * Returns the validated input on success, or a JsonResponse to
     * short-circuit out of the action. Keeps the per-action method
     * readable.
     *
     * @return array{email: string, order_reference: string}|JsonResponse
     */
    protected function validateProofOfOwnership(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'order_reference' => ['required', 'string', 'max:40'],
        ]);
        $email = strtolower($validated['email']);

        $matches = Order::query()
            ->where('reference', $validated['order_reference'])
            ->where('buyer_email', $email)
            ->exists();

        if (! $matches) {
            return response()->json(['error' => 'proof_failed'], 403);
        }

        return ['email' => $email, 'order_reference' => $validated['order_reference']];
    }
}
