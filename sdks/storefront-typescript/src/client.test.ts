import { describe, it, expect } from 'vitest';
import { StorefrontClient } from './client';
import {
  CaptchaRequiredError,
  InventoryUnavailableError,
  PromoInvalidError,
  SessionLockedError,
  StorefrontApiError,
} from './errors';

function mockFetch(routes: Array<{ url: string; status: number; body: unknown; method?: string }>): typeof fetch {
  return async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const url = typeof input === 'string' ? input : (input as URL).toString();
    const method = (init?.method ?? 'GET').toUpperCase();
    const match = routes.find(r => url.endsWith(r.url) && (r.method ?? 'GET').toUpperCase() === method);
    if (!match) {
      throw new Error(`No mock for ${method} ${url}`);
    }
    return new Response(JSON.stringify(match.body), { status: match.status, headers: { 'Content-Type': 'application/json' } });
  };
}

describe('StorefrontClient', () => {
  const baseUrl = 'https://example.test';

  it('lists events', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/events',
      status: 200,
      body: { data: [{ slug: 'a' }], meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 } },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    const r = await c.listEvents();
    expect(r.data).toHaveLength(1);
    expect(r.meta.total).toBe(1);
  });

  it('throws CaptchaRequiredError on 422 captcha_required', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/checkout/sessions',
      status: 422,
      method: 'POST',
      body: { error: 'captcha_required', provider: 'turnstile', site_key: 'xxx' },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    await expect(c.createSession({ eventSlug: 'x' })).rejects.toBeInstanceOf(CaptchaRequiredError);
  });

  it('throws InventoryUnavailableError with structured payload', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/checkout/sessions/abc/items',
      status: 422,
      method: 'PATCH',
      body: { error: 'inventory_unavailable', ticket_category_id: 7, requested: 5, available: 3 },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    try {
      await c.setItems('abc', [{ ticket_category_id: 7, quantity: 5 }]);
      throw new Error('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(InventoryUnavailableError);
      const inv = e as InventoryUnavailableError;
      expect(inv.available).toBe(3);
      expect(inv.requested).toBe(5);
    }
  });

  it('throws PromoInvalidError with reason', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/checkout/sessions/abc/promo',
      status: 422,
      method: 'POST',
      body: { error: 'promo_invalid', reason: 'PROMO_EXPIRED', message: 'That code has expired.' },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    try {
      await c.applyPromo('abc', 'OLD');
      throw new Error('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(PromoInvalidError);
      expect((e as PromoInvalidError).reason).toBe('PROMO_EXPIRED');
    }
  });

  it('throws SessionLockedError on 409', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/checkout/sessions/abc/items',
      status: 409,
      method: 'PATCH',
      body: { error: 'session_locked' },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    await expect(c.setItems('abc', [])).rejects.toBeInstanceOf(SessionLockedError);
  });

  it('falls back to a generic StorefrontApiError', async () => {
    const fetchImpl = mockFetch([{
      url: '/api/v1/public/orders/lookup',
      status: 404,
      method: 'POST',
      body: { error: 'not_found' },
    }]);
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    try {
      await c.lookupOrder('ORD-XX', 'x@y.test');
      throw new Error('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(StorefrontApiError);
      expect((e as StorefrontApiError).status).toBe(404);
      expect((e as StorefrontApiError).code).toBe('not_found');
    }
  });

  it('forwards the captcha token via header on session create', async () => {
    let captured: Record<string, string> | undefined;
    const fetchImpl: typeof fetch = async (_input, init) => {
      captured = init?.headers as Record<string, string>;
      return new Response(JSON.stringify({ data: { uuid: 'sess-1' } }), { status: 201, headers: { 'Content-Type': 'application/json' } });
    };
    const c = new StorefrontClient({ baseUrl, fetch: fetchImpl });
    await c.createSession({ eventSlug: 'x', captchaToken: 'cf-token' });
    expect(captured?.['X-Captcha-Token']).toBe('cf-token');
  });
});
