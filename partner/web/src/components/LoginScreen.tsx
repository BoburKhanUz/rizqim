'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Card } from '@rizqim/ui';
import { useAuth } from './AuthProvider';
import { LanguageSwitcher } from './LanguageSwitcher';
import { LoginForm } from './LoginForm';
import { useT } from './I18nProvider';

export function LoginScreen() {
  const t = useT();
  const router = useRouter();
  const { state } = useAuth();

  // Already signed in (the session was restored from the cookie): nothing to do here
  useEffect(() => {
    if (state.status === 'ready') router.replace('/');
  }, [state.status, router]);

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-6 p-4">
      <div className="flex flex-col items-center gap-1 text-center">
        <span className="flex size-14 items-center justify-center rounded-card bg-brand text-display font-extrabold text-on-brand" aria-hidden="true">
          R
        </span>
        <p className="mt-2 text-title font-bold text-fg">{t('appName')}</p>
        <p className="text-footnote text-fg-muted">{t('appTagline')}</p>
      </div>
      <Card className="w-full max-w-sm p-6">
        <h1 className="text-headline font-bold text-fg">{t('loginTitle')}</h1>
        <p className="mb-5 mt-1 text-footnote text-fg-muted">{t('loginSubtitle')}</p>
        <LoginForm />
      </Card>
      <LanguageSwitcher />
    </main>
  );
}
