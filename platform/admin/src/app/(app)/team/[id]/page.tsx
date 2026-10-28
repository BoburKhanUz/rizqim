import type { Metadata } from 'next';
import { TeamView } from '@/components/team/TeamView';

export const metadata: Metadata = { title: 'Jamoa' };

// /team/<id>: where the search leads for a member: the list with that member highlighted
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  return <TeamView focusId={Number((await params).id) || null} />;
}
