import type { Metadata } from 'next';
import { StoresView } from '@/components/stores/StoresView';

export const metadata: Metadata = { title: 'Ovqatxonalar' };

// P4-22: every store with its health, plan and debt
export default function Page() {
  return <StoresView />;
}
