import type { Metadata } from 'next';
import { TasksView } from '@/components/stores/TasksView';

export const metadata: Metadata = { title: 'Vazifalar' };

// P4-22: CRM-lite tasks across stores and applications
export default function Page() {
  return <TasksView />;
}
