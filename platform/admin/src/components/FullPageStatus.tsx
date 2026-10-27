'use client';

import type { ReactNode } from 'react';
import { Spinner } from '@rizqim/ui';
import { useT } from './I18nProvider';

// A centred screen for "loading" (no children) or for one message with an action (children)
export function FullPageStatus({ children }: { children?: ReactNode }) {
  const t = useT();
  return (
    <main className="flex min-h-screen items-center justify-center p-6">
      {children ?? (
        <div role="status" className="flex items-center gap-3 text-fg-muted">
          <Spinner />
          <span className="text-body">{t('loading')}</span>
        </div>
      )}
    </main>
  );
}
