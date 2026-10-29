import type { Metadata } from 'next';
import { StaffPayView } from '@/components/staffpay/StaffPayView';

export const metadata: Metadata = { title: 'Ish haqi' };

export default function Page() {
  return <StaffPayView />;
}
