<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox\Service;

use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP-served charge endpoint (§7, phase 6 completion). Wire shape:
 *
 *   POST /sandbox/service/v1/charges
 *   Authorization: Bearer {service_key}
 *   {
 *     "merchant": "acme-test",
 *     "amount": "19.99", "currency": "USD",
 *     "reference": "ord_1",
 *     "description": "Cart 1",
 *     "customer": {"name":"…","email":"…","msisdn":"…","ip":"…"},
 *     "return_url": "…", "result_url": "…",
 *     "metadata": {"emulate":"stripe","scenario":"success","pan":"4242…"}
 *   }
 *
 * Response is a normalised JSON envelope — the remote SandboxGateway
 * branch in driver code translates it back into a ChargeResult DTO.
 */
class ChargeServiceController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant' => ['required', 'string', 'exists:sandbox_merchants,slug'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'reference' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:200'],
            'customer.msisdn' => ['nullable', 'string', 'max:32'],
            'customer.ip' => ['nullable', 'string', 'max:45'],
            'return_url' => ['nullable', 'url', 'max:1000'],
            'result_url' => ['nullable', 'url', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $merchant = SandboxMerchant::query()->where('slug', $data['merchant'])->firstOrFail();

        $charge = $this->kernel->charge($merchant, new ChargeRequest(
            amount: Money::ofMajor((string) $data['amount'], strtoupper($data['currency'])),
            customer: new Customer(
                name: $data['customer']['name'] ?? null,
                email: $data['customer']['email'] ?? null,
                msisdn: $data['customer']['msisdn'] ?? null,
                ipAddress: $data['customer']['ip'] ?? $request->ip(),
            ),
            reference: $data['reference'],
            description: $data['description'],
            returnUrl: $data['return_url'] ?? null,
            resultUrl: $data['result_url'] ?? null,
            metadata: (array) ($data['metadata'] ?? []),
        ));

        return response()->json($this->serialise($charge), 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(SandboxTransaction $t): array
    {
        return [
            'uuid' => $t->uuid,
            'reference' => $t->reference,
            'provider_reference' => $t->provider_reference,
            'state' => $t->state,
            'payment_status' => $t->stateEnum->toPaymentStatus()->value,
            'scenario' => $t->scenario,
            'reason_code' => $t->reason_code,
            'amount_minor' => (int) $t->amount_minor,
            'currency' => $t->currency,
            'redirect_url' => $t->redirect_url,
            'poll_url' => $t->poll_url,
            'instructions' => data_get($t->response_body, 'instructions'),
            'response_body' => $t->response_body,
        ];
    }
}
