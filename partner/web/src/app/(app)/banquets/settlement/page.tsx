import { Suspense } from 'react';
import { SettlementView } from '@/components/banquetops/SettlementView';

export default function Page() {
  return (
    <Suspense>
      <SettlementView />
    </Suspense>
  );
}
