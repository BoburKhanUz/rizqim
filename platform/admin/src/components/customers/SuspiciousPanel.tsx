'use client';

import Link from 'next/link';
import { RefreshCw, ShieldCheck } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, InlineError, Pill, Section } from '@rizqim/ui';
import { useSuspicious, type SuspiciousRow } from '@/lib/api/customers';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';

// Shubhali holatlar: read-only signals, each row says in words why it is here and opens the user card. Nothing is decided
// automatically: blocking is an action on the card (with a reason).
export function SuspiciousPanel() {
  const t = useT();
  const cases = useSuspicious();

  if (cases.isError) {
    return <InlineError title={t('customers_loadError')} message={errorText(cases.error, t)} retryLabel={t('retry')} retrying={cases.isFetching} onRetry={() => void cases.refetch()} />;
  }
  if (!cases.data) return <CardSkeletons count={3} lines={2} label={t('loading')} />;

  return (
    <div className="flex max-w-5xl flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-footnote text-fg-muted">{t('customers_suspiciousText', { time: when(cases.data.generated_at) })}</p>
        <Button variant="secondary" icon={RefreshCw} loading={cases.isFetching} onClick={() => void cases.refetch()}>
          {t('customers_refresh')}
        </Button>
      </div>
      {cases.data.groups.map((group) => (
        <Section key={group.kind} title={group.rows.length > 0 ? t('customers_count', { label: group.label, count: group.rows.length }) : group.label}>
          <p className="mb-2 text-footnote text-fg-muted">{group.description}</p>
          {group.rows.length === 0 ? (
            <EmptyState icon={ShieldCheck} title={t('customers_suspiciousEmpty')} />
          ) : (
            <Card padded={false}>
              <ul aria-label={group.label} className="divide-y divide-line">
                {group.rows.map((row, index) => (
                  <SuspiciousItem key={`${row.user.id}-${row.promo_code ?? ''}-${index}`} row={row} />
                ))}
              </ul>
            </Card>
          )}
        </Section>
      ))}
    </div>
  );
}

function SuspiciousItem({ row }: { row: SuspiciousRow }) {
  const t = useT();
  const people = row.accounts.length > 0 ? row.accounts : [row.user];
  return (
    <li className="flex flex-col gap-1.5 px-4 py-3 text-footnote">
      <p className="break-words text-fg">{row.reason}</p>
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        {people.map((person) => (
          <Link key={person.id} href={`/users/${person.id}`} className="inline-flex min-h-11 items-center gap-1.5 font-bold text-brand hover:underline">
            {person.name}
            {person.blocked && <Pill tone="danger">{t('customers_blocked')}</Pill>}
          </Link>
        ))}
        {row.since && <span className="text-fg-muted">{t('customers_lastEvent', { time: when(row.since) })}</span>}
      </div>
    </li>
  );
}
