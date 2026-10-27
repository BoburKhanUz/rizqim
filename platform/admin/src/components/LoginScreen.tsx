'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from './AuthProvider';
import { AuthLayout } from './auth/AuthLayout';
import { LoginFlow } from './auth/LoginFlow';
import { useT } from './I18nProvider';

export function LoginScreen() {
  const t = useT();
  const router = useRouter();
  const { state } = useAuth();

  // Already signed in (the session was restored from the cookie): nothing to do here
  useEffect(() => {
    if (state.status === 'ready') router.replace('/');
  }, [state.status, router]);

  const ended = state.status === 'anonymous' ? state.reason : null;
  return (
    <AuthLayout>
      {ended && (
        <p role="status" className="mb-4 rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">
          {ended === 'idle' ? t('auth_sessionIdle') : t('auth_sessionEnded')}
        </p>
      )}
      <LoginFlow />
    </AuthLayout>
  );
}
