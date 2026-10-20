'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { homePath } from '@/lib/permissions';
import { InlineError } from '@rizqim/ui';
import { useAuth } from './AuthProvider';
import { FullPageStatus } from './FullPageStatus';
import { useT } from './I18nProvider';

// "/" sends a signed-in person to the first section they may open, anyone else to the sign-in page
export function HomeRedirect() {
  const { state, retry } = useAuth();
  const t = useT();
  const router = useRouter();
  useEffect(() => {
    if (state.status === 'anonymous') router.replace('/login');
    if (state.status === 'ready') router.replace(homePath(state.access.permissions) ?? '/dashboard');
  }, [state, router]);
  if (state.status === 'error') {
    return (
      <FullPageStatus>
        <InlineError title={t('loadErrorTitle')} message={t('networkError')} retryLabel={t('retry')} onRetry={retry} className="w-full max-w-md" />
      </FullPageStatus>
    );
  }
  return <FullPageStatus />;
}
