import { describe, it, expect, vi } from 'vitest';
import worker, { type Env } from './index';

function env(overrides: Partial<Env> = {}): Env {
  return {
    ORIGIN_URL: 'https://origin.test',
    QR_HMAC_BYTES: '12',
    QR_SIGNING_SECRET: 'test-secret',
    VOIDED_TICKETS: {
      get: vi.fn().mockResolvedValue(null),
      put: vi.fn(),
      delete: vi.fn(),
      list: vi.fn(),
    } as unknown as KVNamespace,
    ...overrides,
  };
}

async function sign(uuid: string, secret: string, bytes = 12): Promise<string> {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey('raw', enc.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const sig = await crypto.subtle.sign('HMAC', key, enc.encode(uuid));
  return [...new Uint8Array(sig)].map(b => b.toString(16).padStart(2, '0')).join('').slice(0, bytes * 2);
}

const ctx = { waitUntil() {}, passThroughOnException() {} } as unknown as ExecutionContext;

describe('scanner-edge worker', () => {
  it('rejects malformed payload', async () => {
    const req = new Request('https://edge.test/api/v1/scanning/scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payload: 'noseparator' }),
    });
    const res = await worker.fetch(req, env(), ctx);
    expect(res.status).toBe(422);
    expect(await res.json()).toMatchObject({ verdict: 'deny', reason_code: 'malformed_payload' });
  });

  it('rejects bad signature', async () => {
    const req = new Request('https://edge.test/api/v1/scanning/scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payload: 'abc.deadbeef000000000000' }),
    });
    const res = await worker.fetch(req, env(), ctx);
    expect(res.status).toBe(401);
  });

  it('denies a voided ticket from KV cache without origin', async () => {
    const uuid = 'aaaa-bbbb';
    const hmac = await sign(uuid, 'test-secret');
    const kv = { get: vi.fn().mockResolvedValue('refunded'), put: vi.fn(), delete: vi.fn(), list: vi.fn() } as unknown as KVNamespace;
    const req = new Request('https://edge.test/api/v1/scanning/scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payload: `${uuid}.${hmac}` }),
    });
    const res = await worker.fetch(req, env({ VOIDED_TICKETS: kv }), ctx);
    expect(res.status).toBe(200);
    expect(await res.json()).toMatchObject({
      verdict: 'deny',
      reason_code: 'voided_ticket',
      flags: expect.arrayContaining(['edge_void_cache_hit']),
    });
  });

  it('forwards a valid scan to origin', async () => {
    const uuid = 'cccc-dddd';
    const hmac = await sign(uuid, 'test-secret');
    const originResponse = new Response(JSON.stringify({ verdict: 'allow' }), { status: 200 });
    const originalFetch = globalThis.fetch;
    globalThis.fetch = vi.fn().mockResolvedValue(originResponse) as unknown as typeof fetch;
    try {
      const req = new Request('https://edge.test/api/v1/scanning/scan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payload: `${uuid}.${hmac}` }),
      });
      const res = await worker.fetch(req, env(), ctx);
      expect(res.status).toBe(200);
      expect(await res.json()).toMatchObject({ verdict: 'allow' });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it('returns warn-with-pre-check when origin is unreachable', async () => {
    const uuid = 'eeee-ffff';
    const hmac = await sign(uuid, 'test-secret');
    const originalFetch = globalThis.fetch;
    globalThis.fetch = vi.fn().mockRejectedValue(new Error('origin down')) as unknown as typeof fetch;
    try {
      const req = new Request('https://edge.test/api/v1/scanning/scan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payload: `${uuid}.${hmac}` }),
      });
      const res = await worker.fetch(req, env(), ctx);
      expect(res.status).toBe(200);
      expect(await res.json()).toMatchObject({
        verdict: 'warn',
        reason_code: 'origin_unavailable',
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
