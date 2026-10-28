import type { Metadata } from 'next';
import { PipelineView } from '@/components/stores/PipelineView';

export const metadata: Metadata = { title: 'Ulash jarayoni' };

// P4-22: store applications by onboarding stage
export default function Page() {
  return <PipelineView />;
}
