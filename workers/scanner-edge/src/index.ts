/**
 * scanner-edge — Cloudflare Worker that fronts /api/v1/scanning/scan
 * with a fast local verdict before falling through to origin.
 *
 * Why an edge layer:
 *   - Origin scan latency is ~150–300ms (DB hit + fraud rule chain).
 *     Workers verdict is ~5–20ms; saves the gate operator's wait.
 *   - High-volume gates (festivals) generate spikes the origin DB
 *     would struggle with. The edge absorbs 90%+ as a pass-through
 *     200 OK without touching origin.
 *   - Origin outage: the edge can return `verdict=warn` (admit with
 *     manual review) instead of a hard 5xx. Operator's call.
 *
 * Pipeline per scan:
 *   1. Parse the QR payload: `<uuid>.<hmac24hex>`.
 *   2. Recompute HMAC-SHA256(uuid, QR_SIGNING_SECRET) — first
 *      QR_HMAC_BYTES bytes hex-encoded must match `hmac24hex`.
 *   3. Check the VOIDED_TICKETS KV namespace for a hit — denied if
 *      origin has flagged the ticket void / refunded / transferred.
 *   4. (optional) Rate-limit per-device via Durable Object — TBD.
 *   5. Fall through to origin via fetch(). Bearer auth header is
 *      passed through unchanged.
 *   6. Return origin's response verbatim.
 *
 * Origin must be configured to write voids into the KV namespace
 * (see `OfflineTicket::void` observer — TODO on the origin side).
 */
export interface Env {
  ORIGIN_URL: string;
  QR_HMAC_BYTES: string;
  VOIDED_TICKETS: KVNamespace;
  QR_SIGNING_SECRET: string;
}

export default {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(request.url);

    // Only intercept the /scan endpoint — everything else passes through.
    if (request.method !== 'POST' || !url.pathname.endsWith('/api/v1/scanning/scan')) {
      return forward(request, env);
    }

    let body: { payload?: string };
    try {
      body = await request.clone().json();
    } catch {
      return forward(request, env);
    }
    if (!body?.payload) return forward(request, env);

    const verdict = await preCheck(body.payload, env);
    if (verdict.outcome === 'allow_pre_check') {
      // Signature + void-list pass; still call origin so it can
      // apply the full fraud chain + persist the scan event.
      // The pre-check just lets us answer faster when origin is down.
      try {
        return await forward(request, env);
      } catch {
        return jsonResponse(200, {
          verdict: 'warn',
          reason_code: 'origin_unavailable',
          flags: ['edge_pre_check_passed'],
          pre_check: true,
        });
      }
    }

    return jsonResponse(verdict.status, verdict.body);
  },
};

async function preCheck(
  payload: string,
  env: Env,
): Promise<
  | { outcome: 'allow_pre_check' }
  | { outcome: 'deny'; status: number; body: Record<string, unknown> }
> {
  const parts = payload.split('.');
  if (parts.length !== 2) {
    return { outcome: 'deny', status: 422, body: { verdict: 'deny', reason_code: 'malformed_payload' } };
  }
  const [uuid, providedHex] = parts;

  const expected = await hmacHex(uuid!, env.QR_SIGNING_SECRET);
  const want = expected.slice(0, Number(env.QR_HMAC_BYTES) * 2);
  if (!safeEqual(providedHex!, want)) {
    return { outcome: 'deny', status: 401, body: { verdict: 'deny', reason_code: 'invalid_signature' } };
  }

  const voided = await env.VOIDED_TICKETS.get(uuid!);
  if (voided) {
    return {
      outcome: 'deny',
      status: 200,
      body: {
        verdict: 'deny',
        reason_code: 'voided_ticket',
        flags: ['edge_void_cache_hit'],
        voided_reason: voided,
      },
    };
  }

  return { outcome: 'allow_pre_check' };
}

async function hmacHex(message: string, secret: string): Promise<string> {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    'raw',
    enc.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign'],
  );
  const sig = await crypto.subtle.sign('HMAC', key, enc.encode(message));
  return [...new Uint8Array(sig)].map(b => b.toString(16).padStart(2, '0')).join('');
}

/** Constant-time compare to avoid leaking signature info via timing. */
function safeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

async function forward(request: Request, env: Env): Promise<Response> {
  const url = new URL(request.url);
  const target = new URL(env.ORIGIN_URL);
  target.pathname = url.pathname;
  target.search = url.search;

  const init: RequestInit = {
    method: request.method,
    headers: request.headers,
    body: request.method === 'GET' || request.method === 'HEAD' ? undefined : request.body,
  };
  return fetch(target.toString(), init);
}

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'X-Edge-Verdict': 'true',
    },
  });
}
