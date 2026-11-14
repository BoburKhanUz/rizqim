import { Suspense } from 'react';
import { ContractView } from '@/components/banquetops/ContractView';

export default function Page() {
  return (
    <Suspense>
      <ContractView />
    </Suspense>
  );
}
