import type { Metadata } from 'next';
import { Placeholder } from '@/components/Placeholder';

export const metadata: Metadata = { title: 'Mijozlar va yordam' };

// Stage B and later waves replace the placeholder with the real screen (docs/PHASE4_PLAN.md, section 9).
export default function Page() {
  return <Placeholder title="nav_customers" />;
}
