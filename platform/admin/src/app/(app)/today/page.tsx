import type { Metadata } from 'next';
import { TodayView } from '@/components/today/TodayView';

export const metadata: Metadata = { title: 'Bugun' };

// P4-13: the home page after sign-in (the first section of the sidebar, lib/nav.ts)
export default function Page() {
  return <TodayView />;
}
