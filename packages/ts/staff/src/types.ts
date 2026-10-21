import type { Schema } from '@rizqim/api-client';

// The shapes of the API's JSON are the generated ones (packages/ts/api-client/src/schema.d.ts, from the OpenAPI spec);
// nothing here is written by hand. Where the spec is looser or wrong, api/specGaps.ts says so and corrects it.
export type Order = Schema<'Order'>;
export type OrderItem = Schema<'OrderItem'>;
export type OrderStatusHistory = Schema<'OrderStatusHistory'>;
export type OrderStatus = Schema<'OrderStatus'>;
export type OrderType = Schema<'OrderType'>;
export type CancelReason = Schema<'CancelReason'>;
export type PaymentStatus = Schema<'PaymentStatus'>;
export type MenuUnit = Schema<'MenuUnit'>;
export type Product = Schema<'Product'>;
export type ProductVariant = Schema<'ProductVariant'>;
export type Category = Schema<'Category'>;
export type Branch = Schema<'Branch'>;
export type User = Schema<'User'>;
export type UserRole = Schema<'UserRole'>;
export type Store = Schema<'Store'>;
export type StoreStatus = Schema<'StoreStatus'>;
export type PayoutRequest = Schema<'PayoutRequest'>;
export type StoreTransaction = Schema<'StoreTransaction'>;
export type StoreWalletTransaction = Schema<'StoreWalletTransaction'>;
export type MenuBatch = Schema<'MenuBatch'>;

// Reasons a member of staff may give when cancelling or rejecting an order (App\Enums\CancelReason::staffCases):
// payment_timeout is the system's own.
export type StaffCancelReason = Exclude<CancelReason, 'payment_timeout'>;
