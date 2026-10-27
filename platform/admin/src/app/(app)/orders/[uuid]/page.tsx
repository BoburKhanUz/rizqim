import type { Metadata } from 'next';
import { OrderCardView } from '@/components/orders/OrderCardView';

export const metadata: Metadata = { title: 'Buyurtma' };

// P4-14: one marketplace order: history, SLA, money, interventions
export default async function Page({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <OrderCardView uuid={decodeURIComponent(uuid)} />;
}
