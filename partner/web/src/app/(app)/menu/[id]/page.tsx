'use client';

import { useParams } from 'next/navigation';
import { ProductEditor } from '@/components/menu/ProductEditor';

export default function Page() {
  const { id } = useParams<{ id: string }>();
  return <ProductEditor id={id} />;
}
