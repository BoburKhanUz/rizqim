import type { Metadata } from 'next';
import { MarketingView } from '@/components/marketing/MarketingView';

export const metadata: Metadata = { title: 'Marketing' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

// P4-31: banners and promotions, promo codes, categories. ?tab=banners|codes|categories opens a tab.
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  return <MarketingView initialTab={first(params.tab)} />;
}
