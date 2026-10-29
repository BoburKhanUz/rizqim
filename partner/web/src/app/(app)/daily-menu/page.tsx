import type { Metadata } from 'next';
import { DailyMenuView } from '@/components/dailymenu/DailyMenuView';

export const metadata: Metadata = { title: 'Bugungi menyu' };

export default function Page() {
  return <DailyMenuView />;
}
