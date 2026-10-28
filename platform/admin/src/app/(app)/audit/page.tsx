import type { Metadata } from 'next';
import { AuditView } from '@/components/audit/AuditView';

export const metadata: Metadata = { title: 'Harakatlar jurnali' };

export default function Page() {
  return <AuditView />;
}
