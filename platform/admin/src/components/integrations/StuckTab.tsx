'use client';

import Link from 'next/link';
import { CircleCheck } from 'lucide-react';
import { Card, Pill } from '@rizqim/ui';
import { useStuck } from '@/lib/api/integrations';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { ListState } from '../system/parts';
import { EVENT_TONE } from './EventsTab';

// Qotib qolganlar: orders RIZQIM sent to an outside POS that the other side has not confirmed (2 minutes) while the order is still
// open; "Bugun → Diqqat talab qiladi" shows them too. The operator calls the venue or cancels from the order card.
export function StuckTab() {
  const t = useT();
  const stuck = useStuck();
  const data = stuck.data;

  return (
    <div className="flex max-w-4xl flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('integrations_stuck_intro', { minutes: data?.stuck_after_minutes ?? 2 })}</p>
      <ListState
        loading={!data}
        error={stuck.isError ? stuck.error : null}
        fetching={stuck.isFetching}
        onRetry={() => void stuck.refetch()}
        empty={data?.data.length === 0}
        emptyTitle={t('integrations_stuck_none')}
        emptyText={t('integrations_stuck_noneText')}
        icon={CircleCheck}
        errorTitle={t('integrations_loadError')}
      >
        {data && data.count > data.data.length && <p className="text-footnote text-fg-muted">{t('integrations_stuck_more', { shown: data.data.length, count: data.count })}</p>}
        <ul aria-label={t('integrations_stuck_listLabel')} className="flex flex-col gap-2">
          {data?.data.map((row) => (
            <li key={row.event_uuid}>
              <Card className="flex flex-col gap-1.5">
                <div className="flex flex-wrap items-center gap-2">
                  {row.order ? (
                    <Link href={`/orders/${row.order.uuid}`} className="text-body font-bold text-brand underline-offset-2 hover:underline">
                      {t('integrations_stuck_order', { code: row.order.code ?? row.order.uuid.slice(0, 8) })}
                    </Link>
                  ) : (
                    <span className="text-body font-bold text-fg">{t('integrations_stuck_orderGone')}</span>
                  )}
                  <Pill tone={EVENT_TONE[row.status]}>{row.status_label}</Pill>
                  <Pill tone="danger">{t('integrations_stuck_waiting', { minutes: row.waiting_minutes })}</Pill>
                </div>
                <p className="text-footnote text-fg-muted">
                  {row.connection ? `${row.connection.provider_label} · ${row.connection.store?.name ?? '—'}` : '—'} · {when(row.since)}
                  {row.attempts > 1 ? ` · ${t('integrations_ev_attempts', { count: row.attempts })}` : ''}
                </p>
                {row.error && <p className="break-words text-footnote text-danger">{row.error}</p>}
              </Card>
            </li>
          ))}
        </ul>
      </ListState>
    </div>
  );
}
