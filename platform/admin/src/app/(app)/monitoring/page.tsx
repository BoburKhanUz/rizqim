import type { Metadata } from 'next';
import { MonitoringView } from '@/components/monitoring/MonitoringView';
import { isTab } from '@/components/monitoring/tabs';

export const metadata: Metadata = { title: 'Monitoring' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

// /monitoring?tab=status|alerts|jobs|rules&alert=<id>: "Bugun → Diqqat talab qiladi" links to /monitoring?tab=alerts&alert=<id>
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const tab = first(params.tab);
  const alert = Number(first(params.alert));
  return <MonitoringView initialTab={isTab(tab) ? tab : 'status'} focusAlert={Number.isInteger(alert) && alert > 0 ? alert : null} />;
}
