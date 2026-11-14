'use client';

import { useParams } from 'next/navigation';
import { BanquetEventView } from '@/components/banquets/BanquetEventView';

export default function Page() {
  const { uuid } = useParams<{ uuid: string }>();
  return <BanquetEventView uuid={uuid} />;
}
