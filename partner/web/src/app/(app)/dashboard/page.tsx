import type { Metadata } from 'next';
import { Dashboard } from '@/components/Dashboard';

export const metadata: Metadata = { title: 'Boshqaruv paneli' };

export default function DashboardPage() {
  return <Dashboard />;
}
