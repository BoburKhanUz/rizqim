import { Suspense } from 'react';
import { ProductionSheetView } from '@/components/banquetops/ProductionSheetView';

export default function Page() {
  return (
    <Suspense>
      <ProductionSheetView />
    </Suspense>
  );
}
