import type { Metadata } from 'next';
import { FinanceView } from '@/components/finance/FinanceView';
import { FINANCE_TABS, type FinanceTab } from '@/components/finance/financeModel';

export const metadata: Metadata = { title: 'Moliya' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);
const id = (value: string | undefined) => (value && /^[1-9]\d{0,17}$/.test(value) ? Number(value) : null);
const uuid = (value: string | undefined) => (value && /^[0-9a-f-]{36}$/i.test(value) ? value : '');

// P4-23: Moliya. Bugun's rows open it on a record: ?payout=12, ?topup=<uuid>, ?tab=adjustments&adjustment=3
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const tabParam = first(params.tab);
  const payout = id(first(params.payout));
  const adjustment = id(first(params.adjustment));
  const topup = uuid(first(params.topup));
  const tab: FinanceTab = (FINANCE_TABS as readonly string[]).includes(tabParam ?? '')
    ? (tabParam as FinanceTab)
    : topup
      ? 'topups'
      : adjustment !== null
        ? 'adjustments'
        : 'payouts';

  return <FinanceView initial={{ tab, payout, adjustment, topup }} />;
}
