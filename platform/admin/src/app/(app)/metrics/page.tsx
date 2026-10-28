import type { Metadata } from 'next';
import { MetricsView } from '@/components/metrics/MetricsView';

export const metadata: Metadata = { title: "Ko'rsatkichlar" };

// P4-31: MRR, churn, the application funnel and GMV by month (finance.view or stores.view)
export default function Page() {
  return <MetricsView />;
}
