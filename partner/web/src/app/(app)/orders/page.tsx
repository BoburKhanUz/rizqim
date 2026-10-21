import type { Metadata } from 'next';
import { OrdersBoard } from '@/components/orders/OrdersBoard';

export const metadata: Metadata = { title: 'Buyurtmalar' };

export default function OrdersPage() {
  return <OrdersBoard />;
}
