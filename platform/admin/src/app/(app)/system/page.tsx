import type { Metadata } from 'next';
import { SystemView } from '@/components/system/SystemView';
import { isSystemTab } from '@/components/system/tabs';

export const metadata: Metadata = { title: 'Tizim' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

// P4-31 Tizim: /system?tab=settings|payments|locations|flags
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const tab = first(params.tab);
  return <SystemView initialTab={isSystemTab(tab) ? tab : 'settings'} />;
}
