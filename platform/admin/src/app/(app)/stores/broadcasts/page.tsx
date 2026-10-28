import type { Metadata } from 'next';
import { BroadcastsView } from '@/components/stores/BroadcastsView';

export const metadata: Metadata = { title: "E'lonlar" };

// P4-22: Telegram announcements to store owners
export default function Page() {
  return <BroadcastsView />;
}
