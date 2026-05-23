<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Exceptions\GatewayResponseException;
use App\Services\Payments\Exceptions\PaymentException;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Initiates payments. The caller (front-end or another service) POSTs
 * to /payments/charge with amount + customer + which gateway to use;
 * we persist a PaymentTransaction row, hand the ChargeRequest to the
 * driver, and respond with the redirect/poll URLs the front-end needs.
 *
 * Reference handling: callers can supply their own `reference`
 * (an order ID, ticket batch ID). When omitted we mint a ULID so the
 * row is still uniquely identifiable.
 */
class CheckoutController extends Controller
{
    public function __construct(protected PaymentManager $payments) {}

    public function charge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:6'],
            'reference' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:200'],
            'customer.msisdn' => ['nullable', 'string', 'max:32'],
            'return_url' => ['nullable', 'url', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'method' => ['nullable', 'string', 'max:32'],
        ]);

        $reference = $data['reference'] ?? (string) Str::ulid();

        $existing = PaymentTransaction::where('reference', $reference)->first();
        if ($existing !== null) {
            return $this->respondWithTransaction($existing);
        }

        $gateway = $this->payments->gateway($data['gateway']);

        $metadata = (array) ($data['metadata'] ?? []);
        if (! empty($data['method'])) {
            $metadata['method'] = $data['method'];
        }

        $chargeRequest = new ChargeRequest(
            amount: Money::ofMajor((string) $data['amount'], $data['currency']),
            customer: new Customer(
                name: $data['customer']['name'] ?? null,
                email: $data['customer']['email'] ?? null,
                msisdn: $data['customer']['msisdn'] ?? null,
                ipAddress: $request->ip(),
            ),
            reference: $reference,
            description: $data['description'],
            returnUrl: $data['return_url'] ?? null,
            resultUrl: null, // each driver fills its own webhook URL
            metadata: $metadata,
        );

        $transaction = DB::transaction(fn () => PaymentTransaction::create([
            'organization_id' => $request->user()?->currentOrganization?->id,
            'user_id' => $request->user()?->id,
            'gateway' => $gateway->identifier(),
            'reference' => $reference,
            'status' => PaymentStatus::PENDING->value,
            'amount_minor' => $chargeRequest->amount->amountMinor,
            'currency' => $chargeRequest->amount->currency,
            'description' => $chargeRequest->description,
            'customer_email' => $chargeRequest->customer->email,
            'customer_msisdn' => $chargeRequest->customer->normalisedMsisdn(),
            'customer_name' => $chargeRequest->customer->name,
            'customer_ip' => $request->ip(),
            'return_url' => $chargeRequest->returnUrl,
            'metadata' => $metadata,
        ]));

        try {
            $result = $gateway->charge($chargeRequest);
        } catch (GatewayResponseException $e) {
            $transaction->fill([
                'status' => PaymentStatus::FAILED->value,
                'last_response' => $e->response,
                'failed_at' => now(),
            ])->save();

            Log::warning('payment.charge.rejected', [
                'gateway' => $gateway->identifier(),
                'reference' => $reference,
                'message' => $e->getMessage(),
                'code' => $e->gatewayCode,
            ]);

            return response()->json([
                'status' => PaymentStatus::FAILED->value,
                'message' => $e->getMessage(),
                'code' => $e->gatewayCode,
            ], 422);
        } catch (PaymentException $e) {
            $transaction->fill([
                'status' => PaymentStatus::FAILED->value,
                'failed_at' => now(),
            ])->save();

            return response()->json([
                'status' => PaymentStatus::FAILED->value,
                'message' => $e->getMessage(),
            ], 422);
        }

        $transaction->fill([
            'gateway_reference' => $result->gatewayReference,
            'status' => $result->status->value,
            'redirect_url' => $result->redirectUrl,
            'poll_url' => $result->pollUrl,
            'instructions' => $result->instructions,
            'last_response' => $result->raw,
            'settled_at' => $result->status->isSuccessful() ? now() : null,
        ])->save();

        return $this->respondWithTransaction($transaction);
    }

    protected function respondWithTransaction(PaymentTransaction $transaction): JsonResponse
    {
        return response()->json([
            'transaction' => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'gateway' => $transaction->gateway,
                'gateway_reference' => $transaction->gateway_reference,
                'status' => $transaction->status,
                'amount' => number_format($transaction->amount_minor / 100, 2),
                'currency' => $transaction->currency,
                'redirect_url' => $transaction->redirect_url,
                'poll_url' => $transaction->poll_url,
                'instructions' => $transaction->instructions,
            ],
        ]);
    }
}
