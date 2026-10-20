'use client';

import { Hammer } from 'lucide-react';
import { EmptyState } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useT } from './I18nProvider';
import { PageHeader } from './PageHeader';

// A section that is not built yet (the next stages of docs/ROADMAP.md fill them in)
export function Placeholder({ title }: { title: MessageKey }) {
  const t = useT();
  return (
    <>
      <PageHeader title={t(title)} />
      <EmptyState icon={Hammer} title={t('placeholderTitle')} text={t('placeholderText')} />
    </>
  );
}
