'use client';

import { Hammer } from 'lucide-react';
import { EmptyState, PageHeader } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useT } from './I18nProvider';

// A section that is not built yet. Stage B (P4-13 Bugun, P4-14 Buyurtmalar, P4-15 Monitoring) and the later waves replace the
// <Placeholder> in app/(app)/<section>/page.tsx with the real screen.
export function Placeholder({ title }: { title: MessageKey }) {
  const t = useT();
  return (
    <>
      <PageHeader title={t(title)} />
      <EmptyState icon={Hammer} title={t('placeholderTitle')} text={t('placeholderText')} />
    </>
  );
}
