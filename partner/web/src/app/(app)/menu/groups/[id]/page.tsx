'use client';

import { useParams } from 'next/navigation';
import { ModifierGroupEditor } from '@/components/menu/ModifierGroupEditor';

export default function Page() {
  const { id } = useParams<{ id: string }>();
  return <ModifierGroupEditor id={id} />;
}
