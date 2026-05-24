// All public surface wire types. Mirror the JSON shapes returned by
// the Laravel controllers under app/Http/Controllers/Api/Public/.

export type Currency = string; // ISO 4217 (or 'ZiG' for the EcoCash ZiG wallet)

export interface EventSummary {
  event_id: string;
  slug: string;
  name: string;
  short_description: string | null;
  banner_image_path: string | null;
  starts_at: string | null;
  ends_at: string | null;
  city: string | null;
  country_code: string | null;
  is_sold_out: boolean;
  is_featured: boolean;
}

export interface TicketTier {
  uuid: string;
  id: number;
  name: string;
  description: string | null;
  base_price: string;
  base_currency: Currency;
  min_per_order: number;
  max_per_order: number;
  available: number | null;
  currency_prices: Array<{ currency: Currency; price: string }>;
}

export interface EventDetail extends EventSummary {
  description: string | null;
  status: string | null;
  visibility: string | null;
  doors_open_at: string | null;
  timezone: string | null;
  is_online: boolean;
  venue: {
    name: string | null;
    address_line_1: string | null;
    city: string | null;
    country_code: string | null;
    latitude: string | null;
    longitude: string | null;
  };
  capacity: number | null;
  tickets_sold: number;
  organization: {
    uuid: string;
    slug: string;
    name: string;
    logo_url: string | null;
    is_verified: boolean;
  } | null;
  tickets: TicketTier[];
}

export interface PageMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface CartLine {
  ticket_category_id: number;
  quantity: number;
}

export interface SessionSnapshot {
  uuid: string;
  status: 'open' | 'paying' | 'completed' | 'expired' | 'cancelled';
  expires_at: string;
  currency: Currency;
  totals: {
    subtotal_cents: number;
    discount_cents: number;
    tax_cents: number;
    fee_cents: number;
    total_cents: number;
  };
  buyer: {
    name: string | null;
    email: string | null;
    phone: string | null;
    country_code: string | null;
  };
  promo_code: string | null;
  event: { slug: string; name: string; starts_at: string | null } | null;
  items: Array<{
    id: number;
    ticket_category_id: number;
    ticket_name: string;
    quantity: number;
    unit_price_cents: number;
    line_total_cents: number;
    currency: Currency;
  }>;
  order_reference: string | null;
}

export interface PaymentInitiated {
  status: string;
  requires_action: boolean;
  redirect_url: string | null;
  poll_url: string | null;
  instructions: string | null;
  gateway: string;
  transaction_reference: string;
}

export interface ConfirmResult {
  status: 'completed' | 'paying' | 'open' | 'expired' | 'cancelled';
  order_reference?: string;
  order_uuid?: string;
}

export interface OrderDetail {
  reference: string;
  uuid: string;
  status: string;
  placed_at: string | null;
  currency: Currency;
  totals: {
    subtotal_cents: number;
    discount_cents: number;
    tax_cents: number;
    fee_cents: number;
    total_cents: number;
  };
  buyer: { name: string; email: string };
  event: {
    slug: string;
    name: string;
    starts_at: string | null;
    venue_name: string | null;
    city: string | null;
    country_code: string | null;
  } | null;
  items: Array<{
    id: number;
    attendee_name: string;
    attendee_email: string | null;
    ticket_type: string;
    unit_price_cents: number;
    qr_payload: string | null;
    pass_urls: {
      pdf: string;
      apple: string;
      google: string;
    } | null;
  }>;
}

export interface GiftCardBalance {
  code: string;
  balance_cents: number;
  currency: Currency;
  expires_at: string | null;
}

export interface AddonOffer {
  uuid: string;
  name: string;
  description: string | null;
  price_cents: number;
  currency: Currency;
  remaining_stock: number | null;
  min_per_order: number;
  max_per_order: number;
  requires_ticket: boolean;
  image_path: string | null;
}

export interface SeatMapResponse {
  uuid: string;
  name: string;
  layout: Record<string, unknown> | null;
  zones: Record<string, unknown> | null;
  seats: Array<{
    uuid: string;
    zone: string | null;
    row: string | null;
    label: string;
    x: string | null;
    y: string | null;
    status: 'available' | 'held' | 'sold' | 'blocked';
    ticket_category_id: number | null;
  }>;
}

export interface WaitlistEntry {
  uuid: string;
  status: string;
  event_slug: string;
  ticket_category_uuid: string | null;
}

export interface CaptchaConfig {
  provider: 'null' | 'turnstile' | 'hcaptcha' | 'recaptcha';
  site_key: string | null;
}

export interface CreateSessionOptions {
  eventSlug: string;
  currency?: Currency;
  idempotencyKey?: string;
  attribution?: {
    utm_source?: string;
    utm_medium?: string;
    utm_campaign?: string;
    utm_content?: string;
    utm_term?: string;
    referral_source?: string;
    buyer_locale?: string;
    buyer_country_code?: string;
  };
  captchaToken?: string;
}
