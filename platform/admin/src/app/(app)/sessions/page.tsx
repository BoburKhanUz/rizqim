import type { Metadata } from 'next';
import { SessionsView } from '@/components/sessions/SessionsView';

export const metadata: Metadata = { title: 'Sessiyalarim' };

export default function Page() {
  return <SessionsView />;
}
