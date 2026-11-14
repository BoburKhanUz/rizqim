'use client';

import { useState } from 'react';
import { ChartColumn, Download, Percent } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, Field, fieldClass, formatMoney, InlineError, Notice, PageHeader, Skeleton } from '@rizqim/ui';
import { addDays, todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DateField } from '../pos/fields';
import { saveBlob } from '../reports/download';
import { DataTable, Td, Th } from '../reports/ui';
import { CHANNELS, type ChannelRevenue, type CommissionRate, type RevenueFigures, type RevenueFilter } from './api';
import { BrandBadge, channelLabel } from './BrandBadge';
import { useChannelRevenue, useCommissions, useDownloadChannelRevenue, useSaveCommissions } from './hooks';

// /reports/channels "Kanal tushumi" (P6-55): per period × channel × brand — orders, gross, discounts, refunds, the
// channel's commission and the net the kitchen keeps; Excel; the owner's commission percent per channel (an aggregator
// takes its own; without one, the order's own RIZQIM commission counts). The server does every sum.
export function ChannelRevenueView() {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = access.can('reports.view');
  const canSettings = access.can('settings.manage');
  const today = todayIn('Asia/Tashkent');
  const [from, setFrom] = useState(addDays(today, -6));
  const [to, setTo] = useState(today);
  const [settings, setSettings] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const reversed = from !== '' && to !== '' && from > to;
  const filter: RevenueFilter = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const report = useChannelRevenue(filter, canRead && !reversed);
  const download = useDownloadChannelRevenue({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice(t('dk_exportDone', { name: filename }));
    },
    onError: () => setExportError(t('dk_exportFailed')),
  });
  const money = (value: string) => formatMoney(value, language);

  if (access.ready && !canRead) {
    return (
      <>
        <PageHeader title={t('dk_revTitle')} />
        <EmptyState icon={ChartColumn} title={t('mgmt_forbidden')} text={t('dk_revNoAccess')} />
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t('dk_revTitle')}
        subtitle={t('dk_revSubtitle')}
        action={
          <div className="flex flex-wrap gap-2">
            {canSettings && (
              <Button variant="secondary" icon={Percent} onClick={() => setSettings(true)}>
                {t('dk_commissions')}
              </Button>
            )}
            <Button
              variant="secondary"
              icon={Download}
              loading={download.isPending}
              disabled={reversed}
              onClick={() => {
                setExportError(null);
                setNotice(null);
                download.mutate(filter);
              }}
            >
              {download.isPending ? t('dk_exporting') : t('dk_export')}
            </Button>
          </div>
        }
      />
      <Notice message={notice} />
      {exportError && (
        <p role="alert" className="mb-4 text-footnote font-bold text-danger-soft-fg">
          {exportError}
        </p>
      )}
      <Card className="mb-4 flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('report_from')} value={from} onChange={setFrom} />
          <DateField label={t('report_to')} value={to} onChange={setTo} />
          <p className="text-footnote text-fg-muted">{t('dk_revRules')}</p>
        </div>
        {reversed && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {t('report_problem_reversed')}
          </p>
        )}
      </Card>

      {report.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(report.error, t)} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
      ) : !report.data ? (
        <Skeleton className="h-48 w-full" />
      ) : report.data.rows.length === 0 ? (
        <EmptyState icon={ChartColumn} title={t('dk_revEmpty')} text={t('dk_revEmptyText')} />
      ) : (
        <Report data={report.data} money={money} />
      )}

      {settings && (
        <CommissionsDialog
          onClose={() => setSettings(false)}
          onSaved={() => {
            setSettings(false);
            setNotice(t('dk_commissionsSaved'));
          }}
        />
      )}
    </>
  );
}

function Report({ data, money }: { data: ChannelRevenue; money: (value: string) => string }) {
  const { t } = useI18n();
  const figures = (row: RevenueFigures) => (
    <>
      <Td numeric>{row.orders}</Td>
      <Td numeric>{money(row.gross)}</Td>
      <Td numeric>{money(row.discounts)}</Td>
      <Td numeric>{money(row.refunds)}</Td>
      <Td numeric>{money(row.commission)}</Td>
      <Td numeric className="font-bold">
        {money(row.net)}
      </Td>
    </>
  );
  const heads = (
    <>
      <Th numeric>{t('dk_orders')}</Th>
      <Th numeric>{t('dk_gross')}</Th>
      <Th numeric>{t('dk_discounts')}</Th>
      <Th numeric>{t('dk_refunds')}</Th>
      <Th numeric>{t('dk_commission')}</Th>
      <Th numeric>{t('dk_net')}</Th>
    </>
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: t('dk_gross'), value: data.totals.gross },
          { label: t('dk_commission'), value: data.totals.commission },
          { label: t('dk_refunds'), value: data.totals.refunds },
          { label: t('dk_net'), value: data.totals.net },
        ].map((card) => (
          <Card key={card.label} className="flex flex-col gap-1">
            <span className="text-footnote font-bold text-fg-muted">{card.label}</span>
            <span className="text-headline font-extrabold text-fg">{money(card.value)}</span>
          </Card>
        ))}
      </div>
      <Card>
        <DataTable caption={t('dk_revTable')}>
          <thead>
            <tr>
              <Th>{t('dk_channel')}</Th>
              <Th>{t('dk_brand')}</Th>
              {heads}
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row) => (
              <tr key={`${row.channel}:${row.brand_id ?? 0}`}>
                <Td>
                  {channelLabel(row.channel, t)}
                  {row.commission_percent !== null && <span className="text-footnote text-fg-muted"> · {Number(row.commission_percent)}%</span>}
                </Td>
                <Td>{row.brand_id === null ? <span className="text-fg-muted">{t('dk_houseBrand')}</span> : <BrandBadge code={row.brand_code ?? '?'} color={row.brand_color ?? '#64748B'} label={row.brand_name ?? ''} />}</Td>
                {figures(row)}
              </tr>
            ))}
            <tr>
              <Td className="font-bold">{t('dk_total')}</Td>
              <Td />
              {figures(data.totals)}
            </tr>
          </tbody>
        </DataTable>
      </Card>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="mb-2 text-headline font-extrabold text-fg">{t('dk_byChannel')}</h2>
          <DataTable caption={t('dk_byChannel')}>
            <thead>
              <tr>
                <Th>{t('dk_channel')}</Th>
                {heads}
              </tr>
            </thead>
            <tbody>
              {data.by_channel.map((row) => (
                <tr key={row.channel}>
                  <Td>{channelLabel(row.channel, t)}</Td>
                  {figures(row)}
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
        <Card>
          <h2 className="mb-2 text-headline font-extrabold text-fg">{t('dk_byBrand')}</h2>
          <DataTable caption={t('dk_byBrand')}>
            <thead>
              <tr>
                <Th>{t('dk_brand')}</Th>
                {heads}
              </tr>
            </thead>
            <tbody>
              {data.by_brand.map((row) => (
                <tr key={row.brand_id ?? 0}>
                  <Td>{row.brand_id === null ? t('dk_houseBrand') : (row.brand_name ?? '')}</Td>
                  {figures(row)}
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      </div>
    </div>
  );
}

function CommissionsDialog({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const query = useCommissions(true);
  const save = useSaveCommissions(onSaved);
  const [edited, setEdited] = useState<Record<string, { percent: string; note: string }> | null>(null);
  const rates: Record<string, { percent: string; note: string }> =
    edited ?? Object.fromEntries((query.data?.rates ?? []).map((rate: CommissionRate) => [rate.channel, { percent: String(Number(rate.percent)), note: rate.note ?? '' }]));
  const invalid = Object.values(rates).some((rate) => rate.percent.trim() !== '' && (Number.isNaN(Number(rate.percent.replace(',', '.'))) || Number(rate.percent.replace(',', '.')) < 0 || Number(rate.percent.replace(',', '.')) > 100));
  const change = (channel: string, patch: Partial<{ percent: string; note: string }>) => setEdited({ ...rates, [channel]: { percent: '', note: '', ...rates[channel], ...patch } });

  return (
    <Dialog
      open
      onClose={onClose}
      busy={save.isPending}
      closeLabel={t('dk_close')}
      title={t('dk_commissionsTitle')}
      description={t('dk_commissionsHint')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('dk_cancel')}
          </Button>
          <Button
            loading={save.isPending}
            disabled={invalid || query.isPending}
            onClick={() =>
              save.mutate(
                CHANNELS.filter((channel) => (rates[channel]?.percent ?? '').trim() !== '').map((channel) => ({
                  channel,
                  percent: (rates[channel]?.percent ?? '').replace(',', '.').trim(),
                  note: rates[channel]?.note.trim() || null,
                })),
              )
            }
          >
            {t('dk_save')}
          </Button>
        </>
      }
    >
      {query.isError ? (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {errorText(query.error, t)}
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {CHANNELS.map((channel) => (
            <div key={channel} className="flex flex-wrap items-end gap-2">
              <Field label={t('dk_percentOn', { channel: channelLabel(channel, t) })} className="w-40">
                {(control) => <input {...control} className={fieldClass} inputMode="decimal" placeholder="—" value={rates[channel]?.percent ?? ''} onChange={(event) => change(channel, { percent: event.target.value })} />}
              </Field>
              <Field label={t('dk_noteOn', { channel: channelLabel(channel, t) })} className="min-w-48 flex-1">
                {(control) => <input {...control} className={fieldClass} maxLength={255} value={rates[channel]?.note ?? ''} onChange={(event) => change(channel, { note: event.target.value })} />}
              </Field>
            </div>
          ))}
          {invalid && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {t('dk_commissionInvalid')}
            </p>
          )}
          {save.error && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {errorText(save.error, t)}
            </p>
          )}
        </div>
      )}
    </Dialog>
  );
}
