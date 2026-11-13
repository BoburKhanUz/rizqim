'use client';

import { useState } from 'react';
import { Ban, Download, Gift, LockOpen, ScrollText, Search } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, Field, InlineError, Notice, PageHeader, Skeleton, cx, fieldClass, formatDate, formatDateTime, formatMoney } from '@rizqim/ui';
import { addDays, todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DateField } from '../pos/fields';
import { saveBlob } from '../reports/download';
import { DataTable, Pager, Pill, Td, Th, type Tone } from '../reports/ui';
import { VOUCHER_STATUSES, type Voucher, type VoucherFilter, type VoucherStatus } from './api';
import { useBlockVoucher, useDownloadVouchers, useVoucher, useVoucherReport, useVouchers } from './hooks';

const TONES: Record<VoucherStatus, Tone> = { active: 'success', used: 'neutral', blocked: 'danger', expired: 'warning' };

// /vouchers "Sertifikatlar" (P6-43): the gift certificates sold at the till — what was sold and spent in a period and the
// liability left today, the list with each certificate's history, blocking a lost one (vouchers.manage) and the Excel file.
// Selling and spending happen at the till; nothing here changes a balance.
export function VouchersView() {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = access.can('vouchers.manage') || access.can('reports.view');
  const canManage = access.can('vouchers.manage');
  const today = todayIn('Asia/Tashkent');
  const [from, setFrom] = useState(addDays(today, -29));
  const [to, setTo] = useState(today);
  const [status, setStatus] = useState<VoucherStatus | 'all'>('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [history, setHistory] = useState<Voucher | null>(null);
  const [blocking, setBlocking] = useState<Voucher | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const reversed = from !== '' && to !== '' && from > to;
  const range: VoucherFilter = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const q = search.trim();
  const filter: VoucherFilter = { ...(status !== 'all' ? { status } : {}), ...(q ? { q } : {}) };
  const report = useVoucherReport(range, canRead && !reversed);
  const list = useVouchers(filter, page);
  const download = useDownloadVouchers({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice(t('vch_exportDone', { name: filename }));
    },
    onError: () => setExportError(t('vch_exportFailed')),
  });
  const money = (value: string) => formatMoney(value, language);

  if (access.ready && !canRead) {
    return (
      <>
        <PageHeader title={t('vch_title')} />
        <EmptyState icon={Gift} title={t('mgmt_forbidden')} text={t('vch_noAccess')} />
      </>
    );
  }

  const data = report.data;
  const cards = data
    ? [
        { label: t('vch_sold'), amount: data.sold.amount, count: data.sold.count },
        { label: t('vch_redeemed'), amount: data.redeemed.amount, count: data.redeemed.count },
        { label: t('vch_outstanding'), amount: data.outstanding.amount, count: data.outstanding.count, hint: t('vch_outstandingHint', { date: formatDate(data.as_of, language) }) },
        { label: t('vch_blockedExpired'), amount: String(Number(data.blocked.amount) + Number(data.expired.amount)), count: data.blocked.count + data.expired.count },
      ]
    : null;

  return (
    <>
      <PageHeader
        title={t('vch_title')}
        subtitle={t('vch_subtitle')}
        action={
          <Button
            variant="secondary"
            icon={Download}
            loading={download.isPending}
            disabled={reversed}
            onClick={() => {
              setExportError(null);
              setNotice(null);
              download.mutate({ ...filter, ...range });
            }}
          >
            {download.isPending ? t('vch_exporting') : t('vch_export')}
          </Button>
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
          <p className="text-footnote text-fg-muted">{t('vch_exportHint')}</p>
        </div>
        {reversed && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {t('report_problem_reversed')}
          </p>
        )}
        {report.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(report.error, t)} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {cards
              ? cards.map((card) => (
                  <div key={card.label} className="flex flex-col gap-1 rounded-control border border-line p-3">
                    <span className="text-footnote font-bold text-fg-muted">{card.label}</span>
                    <span className="text-headline font-extrabold text-fg">{money(card.amount)}</span>
                    <span className="text-footnote text-fg-muted">{t('vch_countN', { n: card.count })}</span>
                    {card.hint && <span className="text-footnote text-fg-muted">{card.hint}</span>}
                  </div>
                ))
              : [0, 1, 2, 3].map((n) => <Skeleton key={n} className="h-24 w-full" />)}
          </div>
        )}
      </Card>

      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div role="group" aria-label={t('vch_filter')} className="flex flex-wrap gap-1 rounded-control border border-line bg-surface p-1">
            {(['all', ...VOUCHER_STATUSES] as const).map((id) => (
              <button
                key={id}
                type="button"
                aria-pressed={status === id}
                onClick={() => {
                  setStatus(id);
                  setPage(1);
                }}
                className={cx('min-h-11 rounded-control px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-brand', status === id ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:bg-surface-2')}
              >
                {t(`vch_status_${id}`)}
              </button>
            ))}
          </div>
          <Field label={t('vch_search')}>
            {(control) => (
              <div className="relative">
                <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                <input
                  {...control}
                  type="search"
                  value={search}
                  onChange={(event) => {
                    setSearch(event.target.value);
                    setPage(1);
                  }}
                  className={`${fieldClass} pl-9 sm:w-72`}
                />
              </div>
            )}
          </Field>
        </div>

        {list.isError && !list.data ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(list.error, t)} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />
        ) : !list.data ? (
          <div role="status" aria-busy="true" className="flex flex-col gap-2">
            {[0, 1, 2, 3].map((n) => (
              <Skeleton key={n} className="h-10 w-full" />
            ))}
          </div>
        ) : list.data.vouchers.length === 0 ? (
          <EmptyState icon={Gift} title={q || status !== 'all' ? t('vch_noMatch') : t('vch_empty')} text={q || status !== 'all' ? t('vch_noMatchText') : t('vch_emptyText')} />
        ) : (
          <div aria-busy={list.isPlaceholderData || undefined} className={list.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
            <DataTable caption={t('vch_list')}>
              <thead>
                <tr>
                  <Th>{t('vch_code')}</Th>
                  <Th>{t('vch_state')}</Th>
                  <Th numeric>{t('vch_faceValue')}</Th>
                  <Th numeric>{t('vch_balance')}</Th>
                  <Th>{t('vch_soldAt')}</Th>
                  <Th>{t('vch_validUntil')}</Th>
                  <Th>{t('vch_buyer')}</Th>
                  <Th>{t('mgmt_actions')}</Th>
                </tr>
              </thead>
              <tbody>
                {list.data.vouchers.map((voucher) => (
                  <tr key={voucher.uuid}>
                    <Td className="whitespace-nowrap font-mono font-bold">{voucher.code}</Td>
                    <Td>
                      <Pill tone={TONES[voucher.status]}>{t(`vch_status_${voucher.status}`)}</Pill>
                    </Td>
                    <Td numeric className="whitespace-nowrap">
                      {money(voucher.face_value)}
                    </Td>
                    <Td numeric className="whitespace-nowrap font-bold">
                      {money(voucher.balance)}
                    </Td>
                    <Td muted className="whitespace-nowrap">
                      {voucher.sale.sold_at ? formatDateTime(voucher.sale.sold_at, language) : '—'}
                    </Td>
                    <Td muted className="whitespace-nowrap">
                      {voucher.valid_until ? formatDate(voucher.valid_until, language) : t('vch_noEnd')}
                    </Td>
                    <Td muted>{[voucher.buyer.name, voucher.buyer.phone].filter(Boolean).join(', ') || '—'}</Td>
                    <Td>
                      <div className="flex gap-1">
                        <Button variant="secondary" icon={ScrollText} aria-label={t('vch_historyAria', { code: voucher.code })} onClick={() => setHistory(voucher)}>
                          {t('vch_history')}
                        </Button>
                        {canManage &&
                          (voucher.status === 'blocked' ? (
                            <Button variant="secondary" icon={LockOpen} aria-label={t('vch_unblockAria', { code: voucher.code })} onClick={() => setBlocking(voucher)}>
                              {t('vch_unblock')}
                            </Button>
                          ) : voucher.status !== 'used' ? (
                            <Button variant="secondary" icon={Ban} aria-label={t('vch_blockAria', { code: voucher.code })} onClick={() => setBlocking(voucher)}>
                              {t('vch_block')}
                            </Button>
                          ) : null)}
                      </div>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
            <Pager page={list.data.meta.current_page || page} lastPage={list.data.meta.last_page} total={list.data.meta.total} busy={list.isFetching} onPage={setPage} />
          </div>
        )}
      </Card>

      {history && <HistoryDialog voucher={history} onClose={() => setHistory(null)} />}
      {blocking && (
        <BlockDialog
          voucher={blocking}
          onClose={() => setBlocking(null)}
          onDone={(voucher) => {
            setNotice(t(voucher.status === 'blocked' ? 'vch_blocked' : 'vch_unblocked', { code: voucher.code }));
            setBlocking(null);
          }}
        />
      )}
    </>
  );
}

// One certificate: its sale, the buyer and every movement with the balance after it
function HistoryDialog({ voucher, onClose }: { voucher: Voucher; onClose: () => void }) {
  const { t, language } = useI18n();
  const detail = useVoucher(voucher.uuid);
  const money = (value: string) => formatMoney(value, language);
  const shown = detail.data?.voucher ?? voucher;

  return (
    <Dialog
      open
      title={t('vch_historyTitle', { code: voucher.code })}
      closeLabel={t('mgmt_close')}
      onClose={onClose}
      footer={
        <Button variant="secondary" onClick={onClose}>
          {t('mgmt_close')}
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-body">
          <dt className="text-fg-muted">{t('vch_faceValue')}</dt>
          <dd className="font-bold">{money(shown.face_value)}</dd>
          <dt className="text-fg-muted">{t('vch_balance')}</dt>
          <dd className="text-headline font-extrabold">{money(shown.balance)}</dd>
          <dt className="text-fg-muted">{t('vch_state')}</dt>
          <dd>
            <Pill tone={TONES[shown.status]}>{t(`vch_status_${shown.status}`)}</Pill>
          </dd>
          <dt className="text-fg-muted">{t('vch_validUntil')}</dt>
          <dd>{shown.valid_until ? formatDate(shown.valid_until, language) : t('vch_noEnd')}</dd>
          <dt className="text-fg-muted">{t('vch_soldAt')}</dt>
          <dd>
            {shown.sale.sold_at ? formatDateTime(shown.sale.sold_at, language) : '—'} · {t(`vch_tender_${shown.sale.tender}`)}
            {shown.sale.sold_by?.name ? ` · ${t('vch_soldBy')}: ${shown.sale.sold_by.name}` : ''}
          </dd>
          {shown.branch && (
            <>
              <dt className="text-fg-muted">{t('vch_branch')}</dt>
              <dd>{shown.branch.name ?? '—'}</dd>
            </>
          )}
          <dt className="text-fg-muted">{t('vch_buyer')}</dt>
          <dd>{[shown.buyer.name, shown.buyer.phone].filter(Boolean).join(', ') || '—'}</dd>
          <dt className="text-fg-muted">{t('vch_receipt')}</dt>
          <dd>{shown.fiscal.status ?? '—'}</dd>
        </dl>
        {shown.blocked?.reason && <p className="text-footnote font-bold text-danger-soft-fg">{t('vch_blockedReason', { reason: shown.blocked.reason })}</p>}
        {!detail.data ? (
          detail.isError ? (
            <InlineError title={t('loadErrorTitle')} message={errorText(detail.error, t)} retryLabel={t('retry')} retrying={detail.isFetching} onRetry={() => void detail.refetch()} />
          ) : (
            <Skeleton className="h-32 w-full" />
          )
        ) : (
          <DataTable caption={t('vch_entries')}>
            <thead>
              <tr>
                <Th>{t('vch_entryDate')}</Th>
                <Th>{t('vch_entryType')}</Th>
                <Th numeric>{t('vch_entryAmount')}</Th>
                <Th numeric>{t('vch_entryAfter')}</Th>
                <Th>{t('vch_entryBy')}</Th>
              </tr>
            </thead>
            <tbody>
              {detail.data.entries.map((entry) => (
                <tr key={entry.uuid}>
                  <Td muted className="whitespace-nowrap">
                    {entry.created_at ? formatDateTime(entry.created_at, language) : '—'}
                  </Td>
                  <Td>
                    {entry.type_label}
                    {entry.note ? ` — ${entry.note}` : ''}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {entry.type === 'redemption' ? '−' : ''}
                    {money(entry.amount)}
                  </Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {money(entry.balance_after)}
                  </Td>
                  <Td muted>{entry.user?.name ?? '—'}</Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </div>
    </Dialog>
  );
}

// Block (a reason required) or unblock (a reason optional) a certificate
function BlockDialog({ voucher, onClose, onDone }: { voucher: Voucher; onClose: () => void; onDone: (voucher: Voucher) => void }) {
  const { t } = useI18n();
  const block = voucher.status !== 'blocked';
  const [reason, setReason] = useState('');
  const [missing, setMissing] = useState(false);
  const save = useBlockVoucher({ onSuccess: onDone });

  return (
    <Dialog
      open
      variant="center"
      title={t(block ? 'vch_blockTitle' : 'vch_unblockTitle', { code: voucher.code })}
      description={t(block ? 'vch_blockText' : 'vch_unblockText')}
      closeLabel={t('mgmt_close')}
      busy={save.isPending}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button
            variant={block ? 'danger' : 'primary'}
            loading={save.isPending}
            onClick={() => {
              if (block && reason.trim().length < 3) {
                setMissing(true);
                return;
              }
              save.mutate({ uuid: voucher.uuid, block, reason: reason.trim() });
            }}
          >
            {t(block ? 'vch_block' : 'vch_unblock')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <Field label={t(block ? 'vch_reason' : 'vch_reasonOptional')} error={missing ? t('vch_reasonRequired') : undefined}>
          {(control) => (
            <textarea
              {...control}
              value={reason}
              maxLength={300}
              rows={3}
              onChange={(event) => {
                setReason(event.target.value);
                setMissing(false);
              }}
              className={fieldClass}
            />
          )}
        </Field>
        {save.isError && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {errorText(save.error, t)}
          </p>
        )}
      </div>
    </Dialog>
  );
}
