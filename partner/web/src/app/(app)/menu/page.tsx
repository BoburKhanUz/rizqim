import type { Metadata } from 'next';
import { MenuView } from '@/components/menu/MenuView';

export const metadata: Metadata = { title: 'Menyu' };

export default function Page() {
  return <MenuView />;
}
