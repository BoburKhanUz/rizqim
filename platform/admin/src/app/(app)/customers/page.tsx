import type { Metadata } from 'next';
import { CustomersView } from '@/components/customers/CustomersView';
import { isCustomerTab } from '@/components/customers/customersModel';

export const metadata: Metadata = { title: 'Mijozlar va yordam' };

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

const first = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);
const uuid = (value: string | undefined) => (value && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value) ? value.toLowerCase() : null);

// P4-31: Foydalanuvchilar, Shikoyatlar, Shubhali holatlar. Bugun's rows open a complaint: ?tab=complaints&complaint=<uuid>
export default async function Page({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const tab = first(params.tab);
  const complaint = uuid(first(params.complaint));
  return <CustomersView initial={{ tab: isCustomerTab(tab) ? tab : complaint ? 'complaints' : null, complaint }} />;
}
