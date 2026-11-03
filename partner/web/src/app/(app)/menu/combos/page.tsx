import type { Metadata } from 'next';
import { ComboView } from '@/components/combos/ComboView';

export const metadata: Metadata = { title: 'Kombo' };

export default function Page() {
  return <ComboView />;
}
