'use client';

import type { ReactNode } from 'react';
import { CardSkeletons, InlineError } from '@rizqim/ui';
import { useT } from '../I18nProvider';
import { errorText } from '../management/errors';

interface GateQuery<T> {
  data: T | undefined;
  isPending: boolean;
  isError: boolean;
  isFetching: boolean;
  error: unknown;
  refetch: () => unknown;
}

// The three states every list of the POS screens goes through: loading (grey blocks), failed (the server's own words and a
// retry) and loaded. A refetch that fails after a first load keeps the data on screen.
export function Gate<T>({ query, children, count = 2 }: { query: GateQuery<T>; children: (data: T) => ReactNode; count?: number }) {
  const t = useT();
  if (query.data !== undefined) return <>{children(query.data)}</>;
  if (query.isError) {
    return <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  }
  return <CardSkeletons count={count} lines={3} label={t('loading')} />;
}
