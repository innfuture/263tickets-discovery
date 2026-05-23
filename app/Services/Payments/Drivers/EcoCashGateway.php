<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\Enums\PaymentStatus;
use App\Services\Payments\AbstractGateway;
use App\Services\Payments\Contracts\HandlesWebhooks;
use App\Services\Payments\Contracts\PollsTransactionStatus;
use App\Services\Payments\Contracts\RefundsTransactions;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Data\StatusResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Exceptions\GatewayResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * EcoCash Online Merchant integration. Wire shapes follow the supplied
 * EcoCash integration spec (post-ZiG rebase):
 *
 *   Charge   POST {base}/transactions/amount
 *   Refund   POST {base}/transactions/refund
 *   Status   GET  {base}/{msisdn}/transactions/amount/{clientCorrelator}
 *
 * Authentication is HTTP Basic. The merchant credentials (code, PIN,
 * number, terminal) are platform-wide for this deployment.
 */
class EcoCashGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, RefundsTransactions
{
    protected string $identifier = 'ecocash';

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        $payload = $this->buildChargePayload($request);

        $response = $this->httpClient()
            ->withBasicAuth(
                (string) $this->config('auth.username', '', required: true),
                (string) $this->config('auth.password', '', required: true),
            )
            ->post($this->endpoint('/transactions/amount'), $payload);

        $body = $response->json();
        if (! is_array($body)) {
            throw new GatewayResponseException('Malformed EcoCash response.', ['status' => $response->status()]);
        }

        $status = $this->mapStatus((string) ($body['transactionOperationStatus'] ?? ''));

        return new ChargeResult(
            status: $status,
            reference: $request->reference,
            gatewayReference: (string) ($body['serverReferenceCode']
                ?? $body['transactionReference']
                ?? $body['ecocashReference']
                ?? ''),
            redirectUrl: null,
            pollUrl: $this->statusUrl($request->customer->normalisedMsisdn(), $request->reference),
            instructions: __('Approve the prompt on your EcoCash phone to confirm the payment.'),
            raw: $body,
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $payload = [
            'clientCorrelator' => (string) Str::ulid(),
            'notifyUrl' => $this->webhookUrl(),
            'referenceCode' => $request->reference,
            'originalServerReferenceCode' => $request->gatewayReference ?? '',
            'originalEcocashReference' => '',
            'endUserId' => '',
            'transactionOperationStatus' => 'Charged',
            'remark' => $request->reason,
            'paymentAmount' => [
                'charginginformation' => [
                    'amount' => $request->amount?->major() ?? '0.00',
                    'currency' => $request->amount?->currency ?? (string) $this->config('merchant.currency', 'USD'),
                    'description' => $request->reason,
                ],
                'chargeMetaData' => [
                    'channel' => $this->config('channel', 'WEB'),
                    'purchaseCategoryCode' => 'Online Payment',
                    'onBeHalfOf' => $request->metadata['onBehalfOf'] ?? '',
                ],
            ],
            'merchantCode' => (string) $this->config('merchant.code', '', required: true),
            'merchantPin' => (string) $this->config('merchant.pin', '', required: true),
            'merchantNumber' => (string) $this->config('merchant.number', '', required: true),
        ];

        $response = $this->httpClient()
            ->withBasicAuth(
                (string) $this->config('auth.username', '', required: true),
                (string) $this->config('auth.password', '', required: true),
            )
            ->post($this->endpoint('/transactions/refund'), $payload);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return new RefundResult(
            status: $this->mapStatus((string) ($body['transactionOperationStatus'] ?? '')) === PaymentStatus::CAPTURED
                ? PaymentStatus::REFUNDED
                : PaymentStatus::FAILED,
            reference: $request->reference,
            gatewayReference: (string) ($body['serverReferenceCode'] ?? ''),
            raw: $body,
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        // The status endpoint is keyed on msisdn + correlator. Callers
        // pass the correlator as $reference; msisdn is recovered from
        // the PaymentTransaction row before this method is invoked.
        $msisdn = (string) ($this->config('_status_msisdn') ?? '');
        $url = $this->statusUrl($msisdn, $reference);

        $response = $this->httpClient()
            ->withBasicAuth(
                (string) $this->config('auth.username', '', required: true),
                (string) $this->config('auth.password', '', required: true),
            )
            ->get($url);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return new StatusResult(
            status: $this->mapStatus((string) ($body['transactionOperationStatus'] ?? '')),
            reference: $reference,
            gatewayReference: $gatewayReference ?? (string) ($body['serverReferenceCode'] ?? ''),
            message: (string) ($body['responseDescription'] ?? ''),
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        // EcoCash does not sign callbacks; the documented protection is
        // a shared notifyUrl held only by the integrator. We additionally
        // require Basic auth on the callback path (handled at the route
        // middleware level) and the presence of a merchant code match.
        $payload = $request->json()->all();
        $merchant = (string) ($payload['merchantCode'] ?? '');

        return $merchant === (string) $this->config('merchant.code');
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $status = $this->mapStatus((string) ($payload['transactionOperationStatus'] ?? ''));
        $reference = (string) ($payload['referenceCode'] ?? $payload['clientCorrelator'] ?? '');
        $gatewayRef = (string) ($payload['serverReferenceCode'] ?? $payload['ecocashReference'] ?? '');

        $amountInfo = data_get($payload, 'paymentAmount.charginginformation');
        $amount = null;
        if (is_array($amountInfo) && isset($amountInfo['amount'], $amountInfo['currency'])) {
            $amount = Money::ofMajor((string) $amountInfo['amount'], (string) $amountInfo['currency']);
        }

        return new WebhookEvent(
            status: $status,
            reference: $reference !== '' ? $reference : null,
            gatewayReference: $gatewayRef !== '' ? $gatewayRef : null,
            eventType: (string) ($payload['transactionOperationStatus'] ?? 'unknown'),
            amount: $amount,
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildChargePayload(ChargeRequest $request): array
    {
        $merchant = (array) $this->config('merchant', []);

        return [
            'clientCorrelator' => $request->reference,
            'notifyUrl' => $request->resultUrl ?? $this->webhookUrl(),
            'referenceCode' => $request->reference,
            'tranType' => 'MER',
            'endUserId' => $request->customer->normalisedMsisdn() ?? '',
            'remarks' => $request->description,
            'transactionOperationStatus' => 'Charged',
            'paymentAmount' => [
                'charginginformation' => [
                    'amount' => $request->amount->major(),
                    'currency' => $request->amount->currency,
                    'description' => $request->description,
                ],
                'chargeMetaData' => [
                    'channel' => $this->config('channel', 'WEB'),
                    'purchaseCategoryCode' => 'Online Payment',
                    'onBeHalfOf' => $request->customer->name ?? ($merchant['name'] ?? 'Customer'),
                ],
            ],
            'merchantCode' => (string) ($merchant['code'] ?? ''),
            'merchantPin' => (string) ($merchant['pin'] ?? ''),
            'merchantNumber' => (string) ($merchant['number'] ?? ''),
            'currencyCode' => $request->amount->currency,
            'countryCode' => (string) ($merchant['country_code'] ?? 'ZW'),
            'terminalID' => (string) ($merchant['terminal_id'] ?? ''),
            'location' => (string) ($merchant['location'] ?? ''),
            'superMerchantName' => (string) ($merchant['super_name'] ?? ''),
            'merchantName' => (string) ($merchant['name'] ?? ''),
        ];
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) $this->config('base_url', '', required: true), '/').$path;
    }

    protected function statusUrl(?string $msisdn, string $correlator): string
    {
        $base = rtrim((string) $this->config('base_url', '', required: true), '/');

        return $base.'/'.rawurlencode($msisdn ?? '').'/transactions/amount/'.rawurlencode($correlator);
    }

    protected function mapStatus(string $providerStatus): PaymentStatus
    {
        return match (strtolower($providerStatus)) {
            'charged', 'completed', 'successful' => PaymentStatus::CAPTURED,
            'pending', 'initiated' => PaymentStatus::PENDING,
            'refunded', 'reversed' => PaymentStatus::REFUNDED,
            'cancelled', 'canceled' => PaymentStatus::CANCELLED,
            'failed', 'denied', 'rejected' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
