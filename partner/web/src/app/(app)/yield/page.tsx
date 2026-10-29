import type { Metadata } from 'next';
import { YieldView } from '@/components/dailymenu/YieldView';

export const metadata: Metadata = { title: 'Hosil hisoboti' };

export default function Page() {
  return <YieldView />;
}
