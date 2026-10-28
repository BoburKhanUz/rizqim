import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { isCardTab } from '@/components/stores/cardTabs';
import { StoreCardView } from '@/components/stores/StoreCardView';

export const metadata: Metadata = { title: 'Ovqatxona' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

// P4-22: the 360° card of one store; /stores/12?tab=crm opens a tab ("Diqqat talab qiladi" links to the CRM tab)
export default async function Page({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: SearchParams }) {
  const { id } = await params;
  const tab = first((await searchParams).tab);
  if (!/^[1-9]\d{0,17}$/.test(id)) notFound();
  return <StoreCardView id={Number(id)} initialTab={isCardTab(tab) ? tab : 'overview'} />;
}
