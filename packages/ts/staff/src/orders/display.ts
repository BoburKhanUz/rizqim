import type { Order, OrderItem } from '../types';

// The generated schema types a few of an order's JSON columns loosely (`shipping_address` is an object of unknown
// values, an item's `modifiers` a list of them). These read them safely into the shapes the screens use; the columns
// are written by the backend (App\Models\Order, OrderItem), see docs/MENU.md "Buyurtma qatori".

export interface ShippingAddress {
  address: string | null;
  latitude: number | null;
  longitude: number | null;
}

const numberOrNull = (value: unknown): number | null => {
  const number = typeof value === 'string' && value.trim() !== '' ? Number(value) : value;
  return typeof number === 'number' && Number.isFinite(number) ? number : null;
};

// orders.shipping_address (pickup orders carry the branch location); null when the order has none.
export function getShippingAddress(order: Pick<Order, 'shipping_address'>): ShippingAddress | null {
  const raw = order.shipping_address;
  if (!raw || typeof raw !== 'object') return null;
  return {
    address: typeof raw.address === 'string' ? raw.address : null,
    latitude: numberOrNull(raw.latitude),
    longitude: numberOrNull(raw.longitude),
  };
}

// One chosen modifier of an order line, as the order snapshot keeps it.
export interface OrderItemModifier {
  name: string;
  quantity: number;
  group_name: string | null;
  price_delta: string | null;
  total: string | null;
}

export function getItemModifiers(item: Pick<OrderItem, 'modifiers'>): OrderItemModifier[] {
  const modifiers: OrderItemModifier[] = [];
  for (const raw of item.modifiers ?? []) {
    if (typeof raw.name !== 'string') continue;
    modifiers.push({
      name: raw.name,
      quantity: numberOrNull(raw.quantity) ?? 1,
      group_name: typeof raw.group_name === 'string' ? raw.group_name : null,
      price_delta: typeof raw.price_delta === 'string' ? raw.price_delta : null,
      total: typeof raw.total === 'string' ? raw.total : null,
    });
  }
  return modifiers;
}

// "Pepsi × 2, Pishloq": the modifiers of a line as a screen prints them.
export function formatModifiers(modifiers: readonly OrderItemModifier[]): string {
  return modifiers.map((modifier) => (modifier.quantity > 1 ? `${modifier.name} × ${modifier.quantity}` : modifier.name)).join(', ');
}
