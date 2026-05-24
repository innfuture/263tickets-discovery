/**
 * storefront-edge — personalises the public storefront JSON at the
 * edge before hitting origin.
 *
 * Two-stage cache:
 *   1. Cloudflare cache API     short-lived, keyed on URL + country
 *   2. PERSONALISED_CACHE KV    longer-lived per-(path, country) blob
 *
 * Personalisation today:
 *   - currency hint added to event summaries (so the FE can show the
 *     buyer's local price without a separate FX call).
 *   - country-aware featured list (reorder by country match).
 *   - Accept-Language → response `meta.locale` hint.
 *
 * Non-listed paths pass through unchanged.
 */
export interface Env {
  ORIGIN_URL: string;
  PERSONALISE_PATHS: string;
  FALLBACK_CURRENCY: string;
  COUNTRY_CURRENCY: Record<string, string>;
  PERSONALISED_CACHE: KVNamespace;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    const personalisedPaths = env.PERSONALISE_PATHS.split(',').map(p => p.trim());

    // Only intercept the configured paths.
    if (!personalisedPaths.some(p => url.pathname === p || url.pathname.startsWith(p + '?'))) {
      return forward(request, env);
    }
    if (request.method !== 'GET') {
      return forward(request, env);
    }

    const country = (request.headers.get('CF-IPCountry') || 'US').toUpperCase();
    const currency = env.COUNTRY_CURRENCY[country] || env.FALLBACK_CURRENCY;
    const locale = (request.headers.get('Accept-Language') || 'en').split(',')[0]!.trim();

    const cacheKey = `v1:${url.pathname}${url.search}:${country}`;
    const cached = await env.PERSONALISED_CACHE.get(cacheKey, 'text');
    if (cached) {
      return jsonResponse(cached, { 'X-Edge-Cached': 'kv', 'X-Edge-Country': country });
    }

    const originResponse = await forward(request, env);
    if (!originResponse.ok || !originResponse.headers.get('content-type')?.includes('application/json')) {
      return originResponse;
    }

    let body: unknown;
    try {
      body = await originResponse.clone().json();
    } catch {
      return originResponse;
    }

    const personalised = personalise(body, { country, currency, locale });
    const text = JSON.stringify(personalised);

    // Stash in KV honouring the origin's Cache-Control max-age.
    const ttl = parseMaxAge(originResponse.headers.get('cache-control')) ?? 30;
    await env.PERSONALISED_CACHE.put(cacheKey, text, { expirationTtl: ttl });

    return jsonResponse(text, {
      'X-Edge-Cached': 'miss',
      'X-Edge-Country': country,
      'Cache-Control': `public, max-age=${ttl}`,
    });
  },
};

function personalise(body: unknown, ctx: { country: string; currency: string; locale: string }): unknown {
  if (body === null || typeof body !== 'object') return body;
  const root = body as Record<string, unknown>;

  // Add personalisation hints + reorder featured by country match.
  if (Array.isArray(root.data)) {
    root.data = (root.data as unknown[]).map(item => {
      if (item && typeof item === 'object') {
        const obj = item as Record<string, unknown>;
        obj.preferred_currency = ctx.currency;
        return obj;
      }
      return item;
    });

    // Stable sort — country-match goes first, original order otherwise.
    root.data = [...(root.data as Array<Record<string, unknown>>)]
      .map((row, idx) => ({ row, idx, match: row.country_code === ctx.country }))
      .sort((a, b) => (Number(b.match) - Number(a.match)) || (a.idx - b.idx))
      .map(x => x.row);
  }

  // Hint the FE about the resolved locale + currency.
  root.meta = {
    ...(typeof root.meta === 'object' && root.meta !== null ? root.meta as Record<string, unknown> : {}),
    locale: ctx.locale,
    currency: ctx.currency,
    country: ctx.country,
  };

  return root;
}

async function forward(request: Request, env: Env): Promise<Response> {
  const url = new URL(request.url);
  const target = new URL(env.ORIGIN_URL);
  target.pathname = url.pathname;
  target.search = url.search;

  return fetch(target.toString(), {
    method: request.method,
    headers: request.headers,
    body: request.method === 'GET' || request.method === 'HEAD' ? undefined : request.body,
  });
}

function jsonResponse(body: string, extraHeaders: Record<string, string> = {}): Response {
  return new Response(body, {
    status: 200,
    headers: { 'Content-Type': 'application/json', ...extraHeaders },
  });
}

function parseMaxAge(cacheControl: string | null): number | null {
  if (!cacheControl) return null;
  const m = /max-age=(\d+)/.exec(cacheControl);
  return m && m[1] ? parseInt(m[1], 10) : null;
}
