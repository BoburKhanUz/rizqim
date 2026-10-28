'use client';

import type { ReactNode } from 'react';
import { Card } from '@rizqim/ui';
import { useT } from '../I18nProvider';

// The page around every sign-in step: the brand and one card
export function AuthLayout({ children }: { children: ReactNode }) {
  const t = useT();
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-6 p-4">
      <div className="flex flex-col items-center gap-1 text-center">
        <span className="flex size-14 items-center justify-center rounded-card bg-brand text-display font-extrabold text-on-brand" aria-hidden="true">
          R
        </span>
        <p className="mt-2 text-title font-bold text-fg">{t('appName')}</p>
        <p className="text-footnote text-fg-muted">{t('appTagline')}</p>
      </div>
      <Card className="w-full max-w-sm p-6">{children}</Card>
    </main>
  );
}
