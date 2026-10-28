import type { Metadata } from 'next';
import { RolesView } from '@/components/roles/RolesView';

export const metadata: Metadata = { title: 'Rollar' };

export default function Page() {
  return <RolesView />;
}
