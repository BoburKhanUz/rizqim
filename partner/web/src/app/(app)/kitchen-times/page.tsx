import type { Metadata } from 'next';
import { KitchenTimesView } from '@/components/kds/KitchenTimesView';

export const metadata: Metadata = { title: 'Tayyorlash vaqti' };

export default function Page() {
  return <KitchenTimesView />;
}
