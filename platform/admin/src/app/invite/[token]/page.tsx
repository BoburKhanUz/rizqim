import type { Metadata } from 'next';
import { InviteScreen } from '@/components/InviteScreen';

export const metadata: Metadata = { title: 'Taklifni qabul qilish', referrer: 'no-referrer' };

export default async function InvitePage({ params }: { params: Promise<{ token: string }> }) {
  return <InviteScreen token={(await params).token} />;
}
