import type { Metadata } from 'next';
import { IntegrationsView } from '@/components/integrations/IntegrationsView';
import { isIntegrationsTab } from '@/components/integrations/tabs';

export const metadata: Metadata = { title: 'Integratsiyalar' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);
const uuid = (value: string | undefined) => (value && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value) ? value.toLowerCase() : undefined);

// P4-31 Integratsiyalar: /integrations?tab=connections|events|menu|stuck, ?connection=<uuid>, ?event=<uuid> ("Bugun" links to ?tab=stuck)
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const tab = first(params.tab);
  return <IntegrationsView initial={{ tab: isIntegrationsTab(tab) ? tab : undefined, connection: uuid(first(params.connection)), event: uuid(first(params.event)) }} />;
}
