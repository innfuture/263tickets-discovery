<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * UI-only charge initiation — wraps the kernel for the dashboard's
 * scenario panel. Programmatic callers should keep using
 * `Payments::gateway('sandbox')->charge()` via the standard contract.
 */
class ChargeUiController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'merchant' => ['required', 'string', 'exists:sandbox_merchants,slug'],
            'emulate' => ['required', 'string', 'max:32'],
            'method' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'scenario' => ['nullable', 'string', 'max:60'],
            'pan' => ['nullable', 'string', 'max:32'],
            'msisdn' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:200'],
        ]);

        $merchant = SandboxMerchant::query()->where('slug', $data['merchant'])->firstOrFail();

        $metadata = [
            'emulate' => $data['emulate'],
            'method' => $data['method'],
        ];
        if (! empty($data['scenario'])) {
            $metadata['scenario'] = $data['scenario'];
        }
        if (! empty($data['pan'])) {
            $metadata['pan'] = $data['pan'];
        }

        $this->kernel->charge($merchant, new ChargeRequest(
            amount: Money::ofMajor((string) $data['amount'], strtoupper($data['currency'])),
            customer: new Customer(
                name: 'Sandbox dashboard',
                email: $data['email'] ?? null,
                msisdn: $data['msisdn'] ?? null,
                ipAddress: $request->ip(),
            ),
            reference: 'ui_'.(string) Str::ulid(),
            description: 'Triggered from sandbox dashboard',
            metadata: $metadata,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge dispatched.']);

        return back();
    }
}
