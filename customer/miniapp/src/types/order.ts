import type { Amount, Decimal } from './api';
import type { BranchSummary } from './store';
import type { Category, MenuUnit, Product, ProductVariant } from './catalog';
import type { ApiUser } from './user';

// Mirrors App\Enums\OrderStatus.
export type OrderStatus =
  | 'new'
  | 'accepted'
  | 'preparing'
  | 'ready'
  | 'courier_assigned'
  | 'on_the_way'
  | 'delivery' // legacy alias of on_the_way
  | 'delivered'
  | 'canceled'
  | 'rejected'
  | 'refunded';

// Mirrors App\Enums\CancelReason. payment_timeout is the system's own: staff cannot pick it.
export type CancelReason =
  | 'customer_request'
  | 'no_show'
  | 'out_of_stock'
  | 'store_closed'
  | 'courier_unavailable'
  | 'payment_timeout'
  | 'other';

// Mirrors App\Enums\OrderType.
export type OrderType = 'delivery' | 'pickup' | 'dine_in';

// The payment methods POST /orders accepts.
export type PaymentMethod = 'cash' | 'click' | 'payme' | 'uzum' | 'paynet' | 'keshbek';

// Mirrors App\Enums\PaymentStatus.
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'cancelled' | 'expired' | 'refunded';

export interface OrderItemVariant extends ProductVariant {
  product?: (Product & { category?: Category | null }) | null;
}

// One chosen modifier of an order line, as the order snapshot keeps it (docs/MENU.md, "Buyurtma qatori").
export interface OrderItemModifier {
  id: number;
  uuid: string;
  group_id: number;
  group_name: string;
  name: string;
  price_delta: Decimal;
  quantity: number;
  total: Decimal;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_variant_id: number | null;
  product_name: string; // snapshots, so they survive product edits
  variant_name: string | null;
  quantity: number; // a fraction for an item sold by weight (1.5)
  unit?: MenuUnit; // missing on an older backend: pieces
  unit_price: Decimal;
  modifiers?: OrderItemModifier[] | null;
  modifiers_total?: Decimal;
  total_price: Decimal;
  // P6-31: a combo line's parts as sold; the label is how a part reads under the combo (no price)
  combo_parts?: Array<{ slot_name: string; name: string; variant_name: string | null; quantity: number; label: string }> | null;
  created_at: string;
  updated_at: string;
  variant?: OrderItemVariant | null;
}

export interface OrderStatusHistory {
  id: number;
  order_id: number;
  user_id: number | null; // who changed the status
  status: OrderStatus;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

// orders.shipping_address (JSON). Pickup orders carry the branch location.
export interface ShippingAddress {
  address: string | null;
  latitude: number | null;
  longitude: number | null;
}

// P6-34: one packaging line of an order (a bag, a soup bowl): its own line on the receipt; a removed one is 0
export interface OrderPackagingLine {
  rule_id: number;
  name: string;
  quantity: number;
  amount: Decimal;
  removed?: boolean;
}

export interface Order {
  id: number;
  uuid: string;
  short_code: string | null;
  store_id: number;
  user_id: number;
  courier_id: number | null;
  manager_id: number | null;
  branch_id: number | null;
  address_id: number | null;
  promo_code_id: number | null;
  status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: PaymentMethod;
  payment_owner: 'STORE' | 'PLATFORM';
  order_type: OrderType;
  type: OrderType; // appended copy of order_type
  subtotal: Decimal;
  delivery_price: Decimal;
  discount: Decimal;
  total_price: Decimal;
  commission_amount: Decimal; // a `decimal:2` cast, like every money field of the order
  currency: string;
  notes: string | null;
  cancel_reason: CancelReason | null; // set on canceled and rejected orders (older ones have none)
  cancel_note: string | null;
  shipping_address: ShippingAddress | null;
  address: string | null; // appended: shipping_address.address
  // P6-13: the pickup number of the day (a branch with order numbers; null before it is accepted) and where it is
  queue_number?: number | null;
  pickup_status?: 'preparing' | 'ready' | 'handed' | null;
  // P6-34: a pickup for later (the time the customer comes) and the packaging lines added by the branch's rules
  pickup_at?: string | null;
  packaging_amount?: Decimal;
  packaging_lines?: OrderPackagingLine[] | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  items?: OrderItem[];
  // Relations, loaded depending on the caller's role (customers get items only)
  user?: ApiUser | null;
  courier?: ApiUser | null;
  manager?: ApiUser | null;
  branch?: BranchSummary | null;
  status_histories?: OrderStatusHistory[];
}

// GET /orders?scope=active: the non-terminal orders, newest first. `truncated` when there are more than the cap.
export interface ActiveOrdersMeta {
  truncated: boolean;
}

// GET /orders?scope=history: one keyset-paginated page of the terminal orders; next_cursor is null on the last page.
export interface HistoryOrdersMeta {
  next_cursor: string | null;
  per_page: number;
}

// POST /orders/{uuid}/cancel (customer) and the cancel/reject body of POST /orders/{uuid}/status (staff).
export interface CancelOrderPayload {
  reason: CancelReason;
  note?: string;
}

// POST /orders
export interface CreatedOrder {
  order_uuid: string;
  order_number: string | null;
  status: OrderStatus;
  payment_url: string | null;
}

export type PromoType = 'percentage' | 'fixed';

// POST /promo/validate: a preview, the discount is calculated again when the order is placed.
export interface PromoValidation {
  id: number;
  code: string;
  type: PromoType;
  amount: Amount; // the promo's value: a percentage or a sum in UZS
  discount_amount: Amount | null; // what it takes off the subtotal that was sent (the server applies its caps)
  description: string;
}
