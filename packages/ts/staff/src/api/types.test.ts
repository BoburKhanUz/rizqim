import { describe, expectTypeOf, it } from 'vitest';
import type { Schema } from '@rizqim/api-client';
import type { AssignableCourier, ActiveOrders } from './orders';
import type { TopupProvider, OwnerFinance } from './payments';
import type { Batch, StopListEntry, StopListInput } from './menu';
import type { DashboardStats, CreateStoreInput } from './superadmin';
import type { Order } from '../types';
import type { OkData, JsonRequest } from './types';

// The types of the API layer come from the generated schema. These checks fail to COMPILE (tsc --noEmit, part of
// `npm run typecheck`) when the spec drifts from what the screens read, or when a helper type collapses to `never`.
describe('types from the generated schema', () => {
  it('are the schema\'s, not copies', () => {
    expectTypeOf<Order>().toEqualTypeOf<Schema<'Order'>>();
    expectTypeOf<ActiveOrders['orders'][number]>().toEqualTypeOf<Schema<'Order'>>();
  });

  it('are not collapsed to never or any', () => {
    expectTypeOf<AssignableCourier>().toHaveProperty('courier_type');
    expectTypeOf<OwnerFinance>().toHaveProperty('wallet_balance').toBeNumber();
    expectTypeOf<StopListEntry>().toHaveProperty('branch_id').toBeNumber();
    expectTypeOf<Batch>().toHaveProperty('quantity_left');
    expectTypeOf<DashboardStats>().toHaveProperty('total_stores').toBeNumber();
    expectTypeOf<TopupProvider>().toEqualTypeOf<'click' | 'payme'>();
    expectTypeOf<StopListInput['type']>().toEqualTypeOf<'product' | 'variant' | 'modifier'>();
    expectTypeOf<CreateStoreInput>().toHaveProperty('branch_lat').toBeNumber();
    expectTypeOf<OkData<'order.index'>>().not.toBeNever();
    expectTypeOf<JsonRequest<'order.updateStatus'>['status']>().not.toBeNever();
  });
});
