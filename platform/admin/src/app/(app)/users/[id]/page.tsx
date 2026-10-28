import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { UserCardView } from '@/components/customers/UserCardView';

export const metadata: Metadata = { title: 'Foydalanuvchi' };

// P4-31: the user card of Mijozlar va yordam (the search palette's user results open it)
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^[1-9]\d{0,17}$/.test(id)) notFound();
  return <UserCardView id={Number(id)} />;
}
