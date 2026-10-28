import type { Metadata } from 'next';
import { OrdersView } from '@/components/orders/OrdersView';

export const metadata: Metadata = { title: 'Buyurtmalar' };

// P4-14: the marketplace orders of every store, live
export default function Page() {
  return <OrdersView />;
}
