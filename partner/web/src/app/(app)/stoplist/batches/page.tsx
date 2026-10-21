import type { Metadata } from 'next';
import { BatchesView } from '@/components/menu/BatchesView';

export const metadata: Metadata = { title: 'Qozon' };

export default function Page() {
  return <BatchesView />;
}
