'use client';

import { useState, type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { Language } from '@/i18n';
import { AuthProvider } from './AuthProvider';
import { I18nProvider } from './I18nProvider';
import { StepUpProvider } from './StepUpProvider';

// Everything the pages share: language, the query cache, the session and the step-up dialog.
export function Providers({ language, children }: { language: Language; children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          // A failed request is shown with a "Try again" button rather than retried behind the person's back
          queries: { retry: false, refetchOnWindowFocus: true, staleTime: 15_000 },
        },
      }),
  );
  return (
    <I18nProvider language={language}>
      <QueryClientProvider client={queryClient}>
        <StepUpProvider>
          <AuthProvider>{children}</AuthProvider>
        </StepUpProvider>
      </QueryClientProvider>
    </I18nProvider>
  );
}
