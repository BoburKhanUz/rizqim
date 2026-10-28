import type { Metadata } from 'next';
import { PosView } from '@/components/pos/PosView';

export const metadata: Metadata = { title: 'POS va obunalar' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);
const uuid = (value: string | undefined) => (value && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value) ? value.toLowerCase() : undefined);
const id = (value: string | undefined) => (value && /^[1-9]\d{0,17}$/.test(value) ? value : undefined);

// P4-24: subscriptions, tariffs, invoices, devices, shifts, fiscal receipts and offline sync errors. The links of Bugun and of the
// store card open the right tab and row: ?invoice=, ?device=, ?fiscal= (a uuid), ?store= (a store id), also ?shift=, ?sync= and ?tab=.
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  return (
    <PosView
      initial={{
        tab: first(params.tab),
        store: id(first(params.store)),
        invoice: uuid(first(params.invoice)),
        device: uuid(first(params.device)),
        fiscal: uuid(first(params.fiscal)),
        shift: uuid(first(params.shift)),
        sync: uuid(first(params.sync)),
      }}
    />
  );
}
