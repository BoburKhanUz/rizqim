import type { Metadata } from 'next';
import { ApplicationView } from '@/components/stores/ApplicationView';

export const metadata: Metadata = { title: 'Ariza' };

// P4-22: one store application: checklist, stage moves, creating the store, CRM
export default async function Page({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <ApplicationView uuid={decodeURIComponent(uuid)} />;
}
