/**
 * Base for every error the SDK throws. Lets callers `catch (e instanceof StorefrontError)`
 * without depending on the underlying network / validation specifics.
 */
export class StorefrontError extends Error {
  constructor(message: string, public readonly cause?: unknown) {
    super(message);
    this.name = 'StorefrontError';
  }
}

export class StorefrontApiError extends StorefrontError {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly body: unknown,
  ) {
    super(message);
    this.name = 'StorefrontApiError';
  }
}

/** Captcha required by the server. The widget should be rendered with `provider` + `site_key`. */
export class CaptchaRequiredError extends StorefrontApiError {
  constructor(public readonly provider: string, public readonly siteKey: string | null, body: unknown) {
    super(422, 'captcha_required', `Captcha required (${provider})`, body);
    this.name = 'CaptchaRequiredError';
  }
}

/** Inventory ran out while the buyer was holding it. */
export class InventoryUnavailableError extends StorefrontApiError {
  constructor(
    public readonly ticketCategoryId: number,
    public readonly requested: number,
    public readonly available: number,
    body: unknown,
  ) {
    super(422, 'inventory_unavailable',
      `Only ${available} ticket(s) remaining (requested ${requested}).`, body);
    this.name = 'InventoryUnavailableError';
  }
}

export class PromoInvalidError extends StorefrontApiError {
  constructor(public readonly reason: string, message: string, body: unknown) {
    super(422, 'promo_invalid', message, body);
    this.name = 'PromoInvalidError';
  }
}

export class SessionLockedError extends StorefrontApiError {
  constructor(body: unknown) {
    super(409, 'session_locked', 'This checkout session can no longer be modified.', body);
    this.name = 'SessionLockedError';
  }
}
