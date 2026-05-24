import type {
  AddonOffer,
  CaptchaConfig,
  CartLine,
  ConfirmResult,
  CreateSessionOptions,
  EventDetail,
  EventSummary,
  GiftCardBalance,
  OrderDetail,
  PageMeta,
  PaymentInitiated,
  SeatMapResponse,
  SessionSnapshot,
  WaitlistEntry,
} from './types';
import {
  CaptchaRequiredError,
  InventoryUnavailableError,
  PromoInvalidError,
  SessionLockedError,
  StorefrontApiError,
  StorefrontError,
} from './errors';

export interface StorefrontClientOptions {
  baseUrl: string;
  /** Optional captcha token sent on mutating endpoints (`X-Captcha-Token`). */
  captchaToken?: string;
  /** Override the underlying fetch (helpful for tests + non-browser runtimes). */
  fetch?: typeof fetch;
}

/**
 * Thin wrapper over the public storefront REST API. Every method
 * returns a typed payload + throws a typed error on non-2xx.
 *
 * Stateless — no cookies, no localStorage. Pass the checkout session
 * UUID back in every cart-mutation call (mirrors the server which
 * uses the UUID as a capability token).
 */
export class StorefrontClient {
  private readonly baseUrl: string;
  private readonly captchaToken?: string;
  private readonly fetchImpl: typeof fetch;

  constructor(opts: StorefrontClientOptions) {
    this.baseUrl = opts.baseUrl.replace(/\/$/, '');
    this.captchaToken = opts.captchaToken;
    this.fetchImpl = opts.fetch ?? fetch;
  }

  // ── Discovery ──────────────────────────────────────────────────

  async listEvents(params: Record<string, string | number | undefined> = {}): Promise<{ data: EventSummary[]; meta: PageMeta }> {
    const qs = this.qs(params);
    return this.request<{ data: EventSummary[]; meta: PageMeta }>('GET', `/api/v1/public/events${qs}`);
  }

  async featuredEvents(): Promise<{ data: EventSummary[] }> {
    return this.request('GET', '/api/v1/public/events/featured');
  }

  async event(slug: string): Promise<{ data: EventDetail }> {
    return this.request('GET', `/api/v1/public/events/${encodeURIComponent(slug)}`);
  }

  async eventAddons(slug: string): Promise<{ data: AddonOffer[] }> {
    return this.request('GET', `/api/v1/public/events/${encodeURIComponent(slug)}/addons`);
  }

  async eventSeats(slug: string): Promise<{ data: SeatMapResponse | null }> {
    return this.request('GET', `/api/v1/public/events/${encodeURIComponent(slug)}/seats`);
  }

  async captchaConfig(): Promise<{ data: CaptchaConfig }> {
    return this.request('GET', '/api/v1/public/captcha/config');
  }

  // ── Checkout flow ──────────────────────────────────────────────

  async createSession(opts: CreateSessionOptions): Promise<{ data: SessionSnapshot }> {
    return this.request('POST', '/api/v1/public/checkout/sessions', {
      event_slug: opts.eventSlug,
      currency: opts.currency,
      idempotency_key: opts.idempotencyKey,
      attribution: opts.attribution,
    }, { captchaToken: opts.captchaToken ?? this.captchaToken });
  }

  async session(uuid: string): Promise<{ data: SessionSnapshot }> {
    return this.request('GET', `/api/v1/public/checkout/sessions/${uuid}`);
  }

  async setItems(uuid: string, items: CartLine[]): Promise<{ data: SessionSnapshot }> {
    return this.request('PATCH', `/api/v1/public/checkout/sessions/${uuid}/items`, { items });
  }

  async setSeats(uuid: string, seatUuids: string[]): Promise<unknown> {
    return this.request('PATCH', `/api/v1/public/checkout/sessions/${uuid}/seats`, { seat_uuids: seatUuids });
  }

  async setAddons(uuid: string, addons: Array<{ event_addon_uuid: string; quantity: number }>): Promise<unknown> {
    return this.request('PATCH', `/api/v1/public/checkout/sessions/${uuid}/addons`, { addons });
  }

  async setAttendees(uuid: string, payload: {
    buyer_name: string;
    buyer_email: string;
    buyer_phone?: string;
    buyer_country_code?: string;
    attendees?: Array<{ item_id: number; attendees: Array<{ name: string; email?: string; phone?: string }> }>;
  }): Promise<{ data: SessionSnapshot }> {
    return this.request('PATCH', `/api/v1/public/checkout/sessions/${uuid}/attendees`, payload);
  }

  async applyPromo(uuid: string, code: string): Promise<{ data: SessionSnapshot }> {
    return this.request('POST', `/api/v1/public/checkout/sessions/${uuid}/promo`, { code });
  }

  async clearPromo(uuid: string): Promise<{ data: SessionSnapshot }> {
    return this.request('DELETE', `/api/v1/public/checkout/sessions/${uuid}/promo`);
  }

  async applyGiftCard(uuid: string, code: string): Promise<unknown> {
    return this.request('POST', `/api/v1/public/checkout/sessions/${uuid}/gift-card`, { code });
  }

  async clearGiftCard(uuid: string): Promise<unknown> {
    return this.request('DELETE', `/api/v1/public/checkout/sessions/${uuid}/gift-card`);
  }

  async pay(uuid: string, gateway: string, returnUrl?: string): Promise<{ data: { session: SessionSnapshot; payment: PaymentInitiated } }> {
    return this.request('POST', `/api/v1/public/checkout/sessions/${uuid}/pay`, {
      gateway,
      return_url: returnUrl,
    });
  }

  async confirm(uuid: string): Promise<{ data: ConfirmResult }> {
    return this.request('POST', `/api/v1/public/checkout/sessions/${uuid}/confirm`);
  }

  async abandon(uuid: string): Promise<unknown> {
    return this.request('DELETE', `/api/v1/public/checkout/sessions/${uuid}`);
  }

  // ── Orders ─────────────────────────────────────────────────────

  async lookupOrder(reference: string, email: string): Promise<{ data: OrderDetail }> {
    return this.request('POST', '/api/v1/public/orders/lookup', { reference, email });
  }

  async getOrderBySignedUrl(signedUrl: string): Promise<{ data: OrderDetail }> {
    return this.request('GET', this.relativize(signedUrl));
  }

  async requestRefund(reference: string, payload: {
    contact_email: string;
    reason_code: 'duplicate_purchase' | 'event_cancelled' | 'date_change' | 'not_attending' | 'fraud' | 'other';
    notes?: string;
  }): Promise<unknown> {
    return this.request('POST', `/api/v1/public/orders/${encodeURIComponent(reference)}/refund-request`, payload);
  }

  // ── Waitlist + quotes ──────────────────────────────────────────

  async joinWaitlist(slug: string, payload: {
    email: string;
    name?: string;
    phone?: string;
    ticket_category_uuid?: string;
    quantity_requested?: number;
  }): Promise<{ data: WaitlistEntry }> {
    return this.request('POST', `/api/v1/public/events/${encodeURIComponent(slug)}/waitlist`, payload);
  }

  async submitQuote(slug: string, payload: {
    contact_name: string;
    contact_email: string;
    quantity_requested: number;
    contact_phone?: string;
    company_name?: string;
    notes?: string;
    ticket_category_id?: number;
  }): Promise<unknown> {
    return this.request('POST', `/api/v1/public/events/${encodeURIComponent(slug)}/quote`, payload);
  }

  // ── Gift cards ─────────────────────────────────────────────────

  async giftCardBalance(code: string): Promise<{ data: GiftCardBalance }> {
    return this.request('GET', `/api/v1/public/gift-cards/${encodeURIComponent(code)}/balance`);
  }

  // ── Internals ──────────────────────────────────────────────────

  private async request<T>(
    method: string,
    path: string,
    body?: unknown,
    opts: { captchaToken?: string } = {},
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
    };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (opts.captchaToken) headers['X-Captcha-Token'] = opts.captchaToken;

    let response: Response;
    try {
      response = await this.fetchImpl(this.baseUrl + path, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (e) {
      throw new StorefrontError('Network error', e);
    }

    let raw: unknown = null;
    const text = await response.text();
    if (text) {
      try { raw = JSON.parse(text); } catch { raw = text; }
    }

    if (response.ok) return raw as T;

    this.throwTyped(response.status, raw);
  }

  private throwTyped(status: number, body: unknown): never {
    const b = (body && typeof body === 'object' ? body : {}) as Record<string, unknown>;
    const code = String(b['error'] ?? 'http_' + status);
    const message = String(b['message'] ?? code);

    if (status === 422 && code === 'captcha_required') {
      throw new CaptchaRequiredError(String(b['provider'] ?? 'unknown'), (b['site_key'] as string | null) ?? null, body);
    }
    if (status === 422 && code === 'inventory_unavailable') {
      throw new InventoryUnavailableError(
        Number(b['ticket_category_id'] ?? 0),
        Number(b['requested'] ?? 0),
        Number(b['available'] ?? 0),
        body,
      );
    }
    if (status === 422 && code === 'promo_invalid') {
      throw new PromoInvalidError(String(b['reason'] ?? 'unknown'), message, body);
    }
    if (status === 409 && (code === 'session_locked' || code === 'seat_unavailable')) {
      throw new SessionLockedError(body);
    }
    throw new StorefrontApiError(status, code, message, body);
  }

  private qs(params: Record<string, string | number | undefined>): string {
    const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== '');
    if (entries.length === 0) return '';
    const usp = new URLSearchParams();
    for (const [k, v] of entries) usp.append(k, String(v));
    return '?' + usp.toString();
  }

  private relativize(url: string): string {
    try {
      const u = new URL(url);
      return u.pathname + u.search;
    } catch {
      return url;
    }
  }
}
