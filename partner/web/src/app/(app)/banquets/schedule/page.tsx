import { Suspense } from 'react';
import { BanquetScheduleView } from '@/components/banquetpay/BanquetScheduleView';

export default function Page() {
  return (
    <Suspense>
      <BanquetScheduleView />
    </Suspense>
  );
}
