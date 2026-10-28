import type { Metadata } from 'next';
import { OrdersView } from '@/components/orders/OrdersView';

export const metadata: Metadata = { title: 'Buyurtmalar' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);
const id = (value: string | undefined) => (value && /^[1-9]\d{0,17}$/.test(value) ? value : '');

// P4-14: the marketplace orders of every store, live. P4-22: ?store_id= and ?region_id= open it filtered (the store card links here)
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  return <OrdersView initial={{ store_id: id(first(params.store_id)), region_id: id(first(params.region_id)) }} />;
}
