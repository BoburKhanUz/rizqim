'use client';

import { useState } from 'react';
import { ListOrdered } from 'lucide-react';
import { Card, EmptyState, formatTime, Pill, type PillTone } from '@rizqim/ui';
import { useOwnerWaitlist, WAITLIST_STATUSES, type OwnerWaitlistQuery, type WaitlistEntry, type WaitlistStatus, type WaitlistSummary } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Gate } from '../pos/Gate';
import { DateField, SelectField } from '../pos/fields';

// The owner's view of the hostess' waitlist (P6-24): who was written down, for how long they waited against what was quoted and
// how it ended, by business day. Read only; the guests' numbers arrive masked and are shown as they come.

const TONE: Record<WaitlistStatus, PillTone> = { waiting: 'info', seated: 'success', canceled: 'neutral', no_show: 'warning' };

export function WaitlistPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [status, setStatus] = useState<WaitlistStatus | ''>('');

  // Nothing typed: the server's own "today" (the branch's business day)
  const query: OwnerWaitlistQuery = { branch_id: branchId, ...(from ? { from } : {}), ...(to ? { to } : {}), ...(status ? { status } : {}) };
  const list = useOwnerWaitlist(enabled ? query : null);

  if (!enabled) return <p role="note" className="rounded-control bg-info-soft px-3 py-2 text-footnote font-bold text-info-soft-fg">{t('hst_waitlistOff')}</p>;

  return (
    <div className="flex max-w-5xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('hst_wl_intro')}</p>
      <div className="flex flex-wrap items-end gap-3">
        <DateField label={t('hst_wl_from')} value={from} onChange={setFrom} />
        <DateField label={t('hst_wl_to')} value={to} onChange={setTo} min={from || undefined} />
        <SelectField
          label={t('hst_wl_status')}
          value={status}
          onChange={setStatus}
          options={[{ value: '', label: t('hst_wl_allStatuses') }, ...WAITLIST_STATUSES.map((value) => ({ value, label: t(`hst_wl_status_${value}`) }))]}
        />
      </div>
      <Gate query={list} count={3}>
        {(data) => (
          <>
            <Summary summary={data.summary} />
            {data.entries.length === 0 ? <EmptyState icon={ListOrdered} title={t('hst_wl_none')} text={t('hst_wl_noneText')} /> : <EntriesTable entries={data.entries} />}
          </>
        )}
      </Gate>
    </div>
  );
}

function Summary({ summary }: { summary: WaitlistSummary }) {
  const { t } = useI18n();
  const minutes = (value: number | null) => (value === null ? '—' : t('hst_wl_minutes', { n: value }));
  const cells: Array<[string, string]> = [
    [t('hst_wl_total'), String(summary.total)],
    [t('hst_wl_guests'), String(summary.party_size_total)],
    [t('hst_wl_status_seated'), String(summary.seated)],
    [`${t('hst_wl_status_canceled')} / ${t('hst_wl_status_no_show')}`, `${summary.canceled} / ${summary.no_show}`],
    [t('hst_wl_avgQuoted'), minutes(summary.avg_quoted_wait_minutes)],
    [t('hst_wl_avgSeated'), minutes(summary.avg_seated_wait_minutes)],
    [t('hst_wl_longest'), minutes(summary.longest_waiting_minutes)],
  ];
  return (
    <section aria-label={t('hst_wl_summary')}>
      <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {cells.map(([label, value]) => (
          <Card key={label} className="flex flex-col gap-1">
            <dt className="text-footnote text-fg-muted">{label}</dt>
            <dd className="text-title font-bold text-fg">{value}</dd>
          </Card>
        ))}
      </dl>
    </section>
  );
}

function EntriesTable({ entries }: { entries: WaitlistEntry[] }) {
  const { t } = useI18n();
  return (
    <Card padded={false} className="overflow-x-auto">
      <table className="w-full text-left text-body">
        <caption className="sr-only">{t('hst_wl_tableTitle')}</caption>
        <thead>
          <tr className="border-b border-line text-footnote text-fg-muted">
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colTime')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colGuest')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colParty')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colQuoted')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colWaited')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colStatus')}</th>
            <th scope="col" className="px-4 py-3 font-bold">{t('hst_wl_colTable')}</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {entries.map((entry) => {
            const over = entry.quoted_wait_minutes !== null && entry.waited_minutes > entry.quoted_wait_minutes;
            return (
              <tr key={entry.uuid}>
                <td className="whitespace-nowrap px-4 py-3 text-fg">
                  <span className="block text-caption text-fg-muted">{entry.business_date}</span>
                  {entry.created_at ? formatTime(entry.created_at) : '—'}
                </td>
                <th scope="row" className="px-4 py-3 text-left font-bold text-fg">
                  {entry.guest_name}
                  {entry.guest_phone_masked && <span className="block text-caption font-normal text-fg-muted">{entry.guest_phone_masked}</span>}
                </th>
                <td className="px-4 py-3 text-fg">{entry.party_size}</td>
                <td className="px-4 py-3 text-fg">{entry.quoted_wait_minutes === null ? '—' : t('hst_wl_minutes', { n: entry.quoted_wait_minutes })}</td>
                <td className="px-4 py-3 text-fg">
                  {t('hst_wl_minutes', { n: entry.waited_minutes })}
                  {over && <span className="block text-caption font-bold text-warning-soft-fg">{t('hst_wl_over')}</span>}
                </td>
                <td className="px-4 py-3">
                  <Pill tone={TONE[entry.status] ?? 'neutral'}>{t(`hst_wl_status_${entry.status}`)}</Pill>
                </td>
                <td className="px-4 py-3 text-fg">{entry.table_name ?? '—'}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </Card>
  );
}
