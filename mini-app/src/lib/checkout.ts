import type { PaymentSettings } from '../types';

// What the cart offers at checkout, decided by the same rules as the server (App\Actions\Order\CreateOrderAction), so the
// customer is never offered a choice that placing the order would refuse.

// The payment methods the cart offers. Uzum and Paynet are not integrated yet, so they are never offered here,
// whatever a store's payment settings say.
export type CartPaymentMethod = 'naqd' | 'click' | 'payme' | 'keshbek';

// How the customer gets the order: brought to the door, or fetched from a branch.
export type CartOrderType = 'delivery' | 'pickup';

// What a customer who has not picked anything yet is given, as long as the store takes it (else the first method on offer)
const DEFAULT_PAYMENT_METHOD: CartPaymentMethod = 'click';

// PHP's (bool): payment_settings is a JSON column, so a switch can be a boolean, a number, a string or a whole section.
function isTruthy(value: unknown): boolean {
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object' && value !== null) return Object.keys(value).length > 0;
  if (typeof value === 'string') return value !== '' && value !== '0';
  return Boolean(value);
}

// The server reads a provider as `(bool) ($settings[p]['enabled'] ?? ($settings[p] ?? $whenMissing))`: the section's own
// `enabled`, else the section itself (a legacy bare boolean), else the default of a provider the store never configured.
function isProviderOn(section: unknown, whenMissing: boolean): boolean {
  if (typeof section === 'object' && section !== null && !Array.isArray(section)) {
    const enabled = (section as { enabled?: unknown }).enabled;
    if (enabled !== undefined && enabled !== null) return isTruthy(enabled);
  }
  return section === undefined || section === null ? whenMissing : isTruthy(section);
}

// `(bool) ($settings['delivery_enabled'] ?? true)`: both ways of ordering are on unless the store switched one off.
function isOrderTypeOn(flag: unknown): boolean {
  return flag === undefined || flag === null ? true : isTruthy(flag);
}

/**
 * The payment methods a store takes, in the order the cart lists them. Cash is on unless the store switched it off; an
 * online method has to be switched on explicitly, so one the store never set up is off. Settings that did not load
 * (null) are read the same way: as a store that set nothing up. Cashback comes on top whenever the customer has some.
 * Empty when nothing can be paid with.
 */
export function getAllowedPaymentMethods(settings: PaymentSettings | null | undefined, balance: number | undefined): CartPaymentMethod[] {
  const allowed = settings?.allowed_methods || ['cash', 'click', 'payme'];
  const methods: CartPaymentMethod[] = [];

  if ((allowed.includes('cash') || allowed.includes('naqd')) && isProviderOn(settings?.cash, true)) methods.push('naqd');
  if (allowed.includes('click') && isProviderOn(settings?.click, false)) methods.push('click');
  if (allowed.includes('payme') && isProviderOn(settings?.payme, false)) methods.push('payme');
  if (balance !== undefined && balance > 0) methods.push('keshbek');

  return methods;
}

/**
 * The ways of getting the order a store takes: both, unless it switched `delivery_enabled` or `pickup_enabled` off
 * (a store that never set them takes both). Empty when the store takes neither.
 */
export function getAvailableOrderTypes(settings: PaymentSettings | null | undefined): CartOrderType[] {
  const types: CartOrderType[] = [];

  if (isOrderTypeOn(settings?.delivery_enabled)) types.push('delivery');
  if (isOrderTypeOn(settings?.pickup_enabled)) types.push('pickup');

  return types;
}

/**
 * The payment method the order is placed with, never one that is not on offer: the customer's own pick while it is
 * still on offer, else the first method that is. A customer who has not picked yet (`chosen` null) gets Click when the
 * store takes it. Cashback pays for the whole order or not at all, so it only counts when it covers the total.
 * Null when there is nothing to pay with.
 */
export function pickPaymentMethod(
  offered: readonly CartPaymentMethod[],
  chosen: CartPaymentMethod | null,
  isKeshbekAffordable: boolean,
): CartPaymentMethod | null {
  const usable = offered.filter((method) => method !== 'keshbek' || isKeshbekAffordable);
  const wanted = chosen ?? DEFAULT_PAYMENT_METHOD;

  return usable.includes(wanted) ? wanted : (usable[0] ?? null);
}

/**
 * How the order is placed: the customer's own pick while the store still takes it, else the first way it does.
 * Null when the store takes neither.
 */
export function pickOrderType(offered: readonly CartOrderType[], chosen: CartOrderType): CartOrderType | null {
  return offered.includes(chosen) ? chosen : (offered[0] ?? null);
}
