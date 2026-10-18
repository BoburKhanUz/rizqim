import type { Decimal } from './api';
import type { BranchSummary } from './store';
import type { Category, Product, ProductVariant } from './catalog';
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

// Mirrors App\Enums\OrderType.
export type OrderType = 'delivery' | 'pickup' | 'dine_in';

// The payment methods POST /orders accepts.
export type PaymentMethod = 'cash' | 'click' | 'payme' | 'uzum' | 'paynet' | 'keshbek';

// Mirrors App\Enums\PaymentStatus.
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'cancelled' | 'expired' | 'refunded';

export interface OrderItemVariant extends ProductVariant {
  product?: (Product & { category?: Category | null }) | null;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_variant_id: number | null;
  product_name: string; // snapshots, so they survive product edits
  variant_name: string | null;
  quantity: number;
  unit_price: Decimal;
  total_price: Decimal;
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
  commission_amount: number;
  currency: string;
  notes: string | null;
  shipping_address: ShippingAddress | null;
  address: string | null; // appended: shipping_address.address
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

// POST /orders
export interface CreatedOrder {
  order_uuid: string;
  order_number: string | null;
  status: OrderStatus;
  payment_url: string | null;
}
