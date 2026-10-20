import type { Decimal } from './api';

// GET /delivery/quote (App\Domain\Delivery\DeliveryQuote::toArray, docs/ZONES.md). Money is a two-decimal string.
export interface DeliveryQuote {
  zone: {
    id: number;
    name: string;
    delivery_price: Decimal; // the zone's own price; `delivery_price` below is what the customer pays
    min_order_amount: Decimal;
    free_delivery_from: Decimal | null;
    eta_minutes: number | null;
  };
  branch: { id: number; name: string; distance_km: number };
  delivery_price: Decimal; // "0.00" when the free-delivery rule applies
  min_order_amount: Decimal;
  free_delivery_applies: boolean;
  meets_min_order: boolean | null; // null when no subtotal was sent
  eta: { minutes: number } | null;
}

// `data.reason` of a refused quote (422) or a refused order: a stable code the app branches on.
export type DeliveryRefusalReason =
  | 'outside_zone'
  | 'zones_not_configured'
  | 'branch_closed'
  | 'branch_outside_zone'
  | 'below_minimum_order';
