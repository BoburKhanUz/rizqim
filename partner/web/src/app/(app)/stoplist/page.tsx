import type { Metadata } from 'next';
import { StopListView } from '@/components/menu/StopListView';

export const metadata: Metadata = { title: 'Stop-list' };

export default function Page() {
  return <StopListView />;
}
