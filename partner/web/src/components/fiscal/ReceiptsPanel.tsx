'use client';

import { useState } from 'react';
import { ExternalLink, Inbox, RefreshCw } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, InlineError, Skeleton, formatDateTime, formatMoney } from '@rizqim/ui';
import {
  FISCAL_RECEIPT_STATUSES,
  FISCAL_RECEIPT_TYPES,
  useFiscalReceipt,
  useFiscalReceipts,
  useRetryFiscalReceipt,
  type FiscalReceipt,
  type FiscalReceiptsQuery,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Notice } from '../management/Notice';
import { useBranchScope } from '../shell/BranchScope';
import { labelFor } from '../reports/format';
import { HistoryFilters, SelectFilter, usableDates, type DateFilter } from '../reports/HistoryFilters';
import { DataTable, Pager, Pill, Td, Th, type Tone } from '../reports/ui';

const PER_PAGE = 25;
const STATUS_TONE: Record<string, Tone> = { pending: 'warning', issued: 'success', failed: 'danger' };

// A receipt's status in words, with "not fiscal" next to it while the driver is a test or demo one
function StatusPills({ receipt }: { receipt: Pick<FiscalReceipt, 'status' | 'non_fiscal'> }) {
  const { t } = useI18n();
  return (
    <span className="flex flex-wrap items-center gap-1.5">
      <Pill tone={STATUS_TONE[receipt.status] ?? 'neutral'}>{labelFor(t, 'fiscal_rc_st_', receipt.status)}</Pill>
      {receipt.non_fiscal && <Pill tone="neutral">{t('fiscal_rc_nonFiscal')}</Pill>}
    </span>
  );
}

// The fiscal receipts of the store: filters, a table, the receipt with its lines and a retry for a pending or failed one.
export function ReceiptsPanel({ canRetry }: { canRetry: boolean }) {
  const { t, language } = useI18n();
  const { branchId } = useBranchScope();
  const [status, setStatus] = useState('');
  const [type, setType] = useState('');
  const [dates, setDates] = useState<DateFilter>({ from: '', to: '' });
  const [page, setPage] = useState(1);
  const [openUuid, setOpenUuid] = useState<string | null>(null);
  const usable = usableDates(dates);
  const query: FiscalReceiptsQuery = {
    ...(branchId ? { branch_id: branchId } : {}),
    ...(status ? { status: status as (typeof FISCAL_RECEIPT_STATUSES)[number] } : {}),
    ...(type ? { type: type as (typeof FISCAL_RECEIPT_TYPES)[number] } : {}),
    ...(usable.from ? { from: usable.from } : {}),
    ...(usable.to ? { to: usable.to } : {}),
    page,
    per_page: PER_PAGE,
  };
  const receipts = useFiscalReceipts(query, { enabled: !usable.reversed });
  const reset = <T,>(set: (value: T) => void) => (value: T) => {
    set(value);
    setPage(1);
  };

  return (
    <Card className="flex flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('fiscal_rc_intro')}</p>
      <HistoryFilters value={dates} reversed={usable.reversed} onChange={reset(setDates)}>
        <SelectFilter label={t('fiscal_rc_status')} allLabel={t('fiscal_rc_all')} value={status} onChange={reset(setStatus)} options={FISCAL_RECEIPT_STATUSES.map((value) => ({ value, label: labelFor(t, 'fiscal_rc_st_', value) }))} />
        <SelectFilter label={t('fiscal_rc_type')} allLabel={t('fiscal_rc_all')} value={type} onChange={reset(setType)} options={FISCAL_RECEIPT_TYPES.map((value) => ({ value, label: labelFor(t, 'fiscal_rc_ty_', value) }))} />
      </HistoryFilters>

      {receipts.isError && !receipts.data ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(receipts.error, t)} retryLabel={t('retry')} retrying={receipts.isFetching} onRetry={() => void receipts.refetch()} />
      ) : !receipts.data ? (
        <div role="status" aria-busy="true" className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((n) => (
            <Skeleton key={n} className="h-10 w-full" />
          ))}
        </div>
      ) : receipts.data.data.length === 0 ? (
        <EmptyState icon={Inbox} title={t('fiscal_rc_empty')} text={t('fiscal_rc_emptyText')} />
      ) : (
        <div aria-busy={receipts.isPlaceholderData || undefined} className={receipts.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
          <DataTable caption={t('fiscal_tab_receipts')}>
            <thead>
              <tr>
                <Th>{t('fiscal_rc_colDate')}</Th>
                <Th>{t('fiscal_rc_colType')}</Th>
                <Th>{t('fiscal_rc_colStatus')}</Th>
                <Th>{t('fiscal_rc_colForm')}</Th>
                <Th numeric>{t('fiscal_rc_colTotal')}</Th>
                <Th>{t('fiscal_rc_colSign')}</Th>
                <Th numeric>{t('fiscal_rc_colAttempts')}</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {receipts.data.data.map((receipt) => (
                <tr key={receipt.uuid}>
                  <Td className="whitespace-nowrap">{formatDateTime(receipt.created_at, language)}</Td>
                  <Td>{labelFor(t, 'fiscal_rc_ty_', receipt.type)}</Td>
                  <Td>
                    <StatusPills receipt={receipt} />
                  </Td>
                  <Td>{labelFor(t, 'fiscal_rc_form_', receipt.payment_form)}</Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {formatMoney(receipt.total, language)}
                  </Td>
                  <Td muted className="font-mono text-footnote break-all">
                    {receipt.fiscal_sign || '—'}
                  </Td>
                  <Td numeric muted>
                    {receipt.attempts}
                  </Td>
                  <Td>
                    <Button variant="secondary" aria-label={t('fiscal_rc_openAria', { date: formatDateTime(receipt.created_at, language), total: formatMoney(receipt.total, language) })} onClick={() => setOpenUuid(receipt.uuid)}>
                      {t('fiscal_rc_open')}
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
          <Pager page={receipts.data.current_page || page} lastPage={receipts.data.last_page} total={receipts.data.total} busy={receipts.isFetching} onPage={setPage} />
        </div>
      )}

      {openUuid && <ReceiptDialog uuid={openUuid} canRetry={canRetry} onClose={() => setOpenUuid(null)} />}
    </Card>
  );
}

function ReceiptDialog({ uuid, canRetry, onClose }: { uuid: string; canRetry: boolean; onClose: () => void }) {
  const { t, language } = useI18n();
  const receipt = useFiscalReceipt(uuid);
  const retry = useRetryFiscalReceipt();
  const [done, setDone] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const data = receipt.data;
  const retryable = data !== undefined && (data.status === 'pending' || data.status === 'failed');

  const send = () => {
    setDone(null);
    setFailure(null);
    retry.mutate(uuid, { onSuccess: () => setDone(t('fiscal_rc_retried')), onError: (error) => setFailure(errorText(error, t)) });
  };

  return (
    <Dialog
      open
      title={t('fiscal_rc_detail')}
      description={data ? `${labelFor(t, 'fiscal_rc_ty_', data.type)} · ${formatDateTime(data.created_at, language)}` : undefined}
      closeLabel={t('mgmt_close')}
      onClose={onClose}
      busy={retry.isPending}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={retry.isPending}>
            {t('mgmt_close')}
          </Button>
          {retryable && canRetry && (
            <Button icon={RefreshCw} onClick={send} loading={retry.isPending}>
              {t('fiscal_rc_retry')}
            </Button>
          )}
        </>
      }
    >
      {!data ? (
        receipt.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(receipt.error, t)} retryLabel={t('retry')} retrying={receipt.isFetching} onRetry={() => void receipt.refetch()} />
        ) : (
          <Skeleton className="h-40 w-full" />
        )
      ) : (
        <div className="flex flex-col gap-4">
          <Notice message={done} />
          {failure && (
            <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
              {failure}
            </p>
          )}
          <div className="flex flex-wrap items-center gap-2">
            <StatusPills receipt={data} />
            <span className="text-headline font-extrabold text-fg">{formatMoney(data.total, language)}</span>
          </div>
          {data.error && (
            <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote text-danger-soft-fg">
              <span className="font-bold">{t('fiscal_rc_error')}: </span>
              {data.error}
            </p>
          )}
          {retryable && <p className="text-footnote text-fg-muted">{canRetry ? t('fiscal_rc_retryHint') : t('fiscal_rc_retryOwnerOnly')}</p>}

          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-footnote">
            <Row label={t('fiscal_rc_driver')} value={data.driver} />
            <Row label={t('fiscal_rc_provider')} value={data.provider} />
            <Row label={t('fiscal_rc_regime')} value={data.tax_regime ? labelFor(t, 'fiscal_regime_', data.tax_regime) : null} />
            <Row label={t('fiscal_rc_number')} value={data.receipt_number} mono />
            <Row label={t('fiscal_rc_colSign')} value={data.fiscal_sign} mono />
            <Row label={t('fiscal_rc_terminal')} value={data.terminal_id} mono />
            <Row label={t('fiscal_rc_vat')} value={formatMoney(data.vat_total, language)} />
            <Row label={t('fiscal_rc_colAttempts')} value={String(data.attempts)} />
            <Row label={t('fiscal_rc_createdAt')} value={formatDateTime(data.created_at, language)} />
            <Row label={t('fiscal_rc_issuedAt')} value={data.issued_at ? formatDateTime(data.issued_at, language) : null} />
            <Row label={t('fiscal_rc_nextAttempt')} value={data.next_attempt_at ? formatDateTime(data.next_attempt_at, language) : null} />
            <Row label={t('fiscal_rc_original')} value={data.original_receipt_uuid ? data.original_receipt_uuid.slice(0, 8).toUpperCase() : null} mono />
          </dl>
          {data.qr_url && (
            <a href={data.qr_url} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-2 text-body font-bold text-brand underline">
              <ExternalLink aria-hidden="true" className="size-4" />
              {t('fiscal_rc_qr')}
            </a>
          )}

          <section aria-label={t('fiscal_rc_lines')}>
            <h3 className="mb-1 text-body font-bold text-fg">{t('fiscal_rc_lines')}</h3>
            <DataTable caption={t('fiscal_rc_lines')}>
              <thead>
                <tr>
                  <Th>{t('fiscal_rc_lineName')}</Th>
                  <Th>{t('fiscal_rc_lineMxik')}</Th>
                  <Th numeric>{t('fiscal_rc_lineQty')}</Th>
                  <Th numeric>{t('fiscal_rc_linePrice')}</Th>
                  <Th numeric>{t('fiscal_rc_lineAmount')}</Th>
                  <Th numeric>{t('fiscal_rc_lineVat')}</Th>
                </tr>
              </thead>
              <tbody>
                {data.lines.map((line, index) => (
                  <tr key={`${line.name}-${index}`}>
                    <Td>{line.name}</Td>
                    <Td muted className="font-mono text-footnote">
                      {line.mxik_code}
                    </Td>
                    <Td numeric>{Number(line.quantity)}</Td>
                    <Td numeric className="whitespace-nowrap">
                      {formatMoney(line.unit_price, language)}
                    </Td>
                    <Td numeric className="whitespace-nowrap font-bold">
                      {formatMoney(line.amount, language)}
                    </Td>
                    <Td numeric muted className="whitespace-nowrap">
                      {line.no_vat ? t('fiscal_rc_noVat') : `${Number(line.vat_rate)}% · ${formatMoney(line.vat_amount, language)}`}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
          </section>

          <section aria-label={t('fiscal_rc_payments')}>
            <h3 className="mb-1 text-body font-bold text-fg">{t('fiscal_rc_payments')}</h3>
            <ul className="flex flex-col gap-1 text-body text-fg">
              {data.payments.map((payment, index) => (
                <li key={index} className="flex flex-wrap items-center gap-2">
                  <span>{labelFor(t, 'fiscal_rc_form_', payment.form)}</span>
                  <span className="font-bold">{formatMoney(payment.amount, language)}</span>
                  {payment.prepayment && <Pill tone="info">{t('fiscal_rc_prepayment')}</Pill>}
                  {payment.credit && <Pill tone="warning">{t('fiscal_rc_credit')}</Pill>}
                </li>
              ))}
            </ul>
          </section>
        </div>
      )}
    </Dialog>
  );
}

function Row({ label, value, mono }: { label: string; value: string | null | undefined; mono?: boolean }) {
  if (value === null || value === undefined || value === '') return null;
  return (
    <>
      <dt className="text-fg-muted">{label}</dt>
      <dd className={mono ? 'break-all font-mono text-fg' : 'text-fg'}>{value}</dd>
    </>
  );
}
