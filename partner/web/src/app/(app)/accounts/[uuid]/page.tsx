'use client';

import { useParams } from 'next/navigation';
import { CorporateAccountView } from '@/components/accounts/CorporateAccountView';

export default function Page() {
  const { uuid } = useParams<{ uuid: string }>();
  return <CorporateAccountView uuid={uuid} />;
}
