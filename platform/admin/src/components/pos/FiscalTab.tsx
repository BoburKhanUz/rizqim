'use client';

import { useEffect, useState } from 'react';
import { ChevronRight, Receipt, Search } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Field, fieldClass, InlineError, Notice, Pill, Section } from '@rizqim/ui';
import { DEFAULT_FISCAL_FILTERS, FISCAL_STATUSES, useFiscal, useFiscalCard, useRetryFiscal, type FiscalFilters, type FiscalRow } from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { FISCAL_LABEL, FISCAL_TONE, fiscalLabel, money } from './posModel';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager, ReasonDialog } from './PosParts';

// Fiskal cheklar (P4-24): failed and pending receipts of every store with the provider's reason, the errors per provider and
// "Qayta yuborish" (stores.manage: a reason and the step-up). A receipt Payme or Click registered themselves cannot be retried here.

export function FiscalTab({ selected, onSelect }: { selected: string | null; onSelect: (uuid: string | null) => void }) {
  if (selected) return <FiscalCardView uuid={selected} onBack={() => onSelect(null)} />;
  return <FiscalList onSelect={onSelect} />;
}

function FiscalList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<FiscalFilters>(DEFAULT_FISCAL_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const receipts = useFiscal(filters);
  const page = receipts.data;
  const summary = page?.summary;
  const set = <K extends keyof FiscalFilters>(name: K, value: FiscalFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      {summary && summary.by_driver.length > 0 && (
        <Section title={t('pos_fis_perProvider')}>
          <ul aria-label={t('pos_fis_perProvider')} className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {summary.by_driver.map((row) => (
              <li key={row.driver}>
                <Card className="flex flex-col gap-1">
                  <button
                    type="button"
                    aria-pressed={filters.driver === row.driver}
                    onClick={() => set('driver', filters.driver === row.driver ? '' : row.driver)}
                    className="flex flex-col gap-1 rounded-control text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                  >
                    <span className="flex items-center gap-2">
                      <span className="text-body font-bold text-fg">{row.driver}</span>
                      {filters.driver === row.driver && <Pill tone="info">{t('pos_fis_filtered')}</Pill>}
                    </span>
                    <span className="text-footnote text-fg-muted">{t('pos_fis_providerCounts', { failed: row.failed, pending: row.pending })}</span>
                    {row.last_error && <span className="break-words text-footnote text-fg">{row.last_error}</span>}
                  </button>
                </Card>
              </li>
            ))}
          </ul>
        </Section>
      )}
      <ChipRow label={t('pos_fis_statusFilter')}>
        {FISCAL_STATUSES.map((status) => (
          <Chip key={status} on={filters.status === status} onClick={() => set('status', status)}>
            {status === 'open' && summary ? t('pos_countLabel', { label: t(FISCAL_LABEL[status]), count: summary.failed + summary.pending }) : t(FISCAL_LABEL[status])}
          </Chip>
        ))}
      </ChipRow>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={t('pos_search')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('pos_fis_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
      </div>

      <ListState
        loading={!page}
        error={receipts.isError ? receipts.error : null}
        fetching={receipts.isFetching}
        onRetry={() => void receipts.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={t('pos_fis_empty')}
        emptyText={t('pos_fis_emptyText')}
        icon={Receipt}
        errorTitle={t('pos_loadError')}
      >
        <p className="text-footnote text-fg-muted" aria-live="polite">
          {t('pos_total', { count: page?.total ?? 0 })}
        </p>
        <ul aria-label={t('pos_fis_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => <FiscalListRow key={row.uuid} row={row} onSelect={() => onSelect(row.uuid)} />)}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => set('page', next)} />}
      </ListState>
    </div>
  );
}

function FiscalListRow({ row, onSelect }: { row: FiscalRow; onSelect: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onSelect}
          aria-label={t('pos_fis_openRow', { store: row.store?.name ?? '', total: money(row.total) })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">
                {row.store?.name ?? '—'} · {money(row.total)}
              </span>
              <Pill tone={FISCAL_TONE[row.status] ?? 'neutral'}>{t(fiscalLabel(row.status))}</Pill>
              {!row.retryable && row.status !== 'issued' && <Pill tone="neutral">{t('pos_fis_external')}</Pill>}
            </span>
            {row.error && <span className="break-words text-footnote text-fg">{row.error}</span>}
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.driver}</span>
              {row.branch && <span>{row.branch.name}</span>}
              <span>{t('pos_fis_attempts', { count: row.attempts })}</span>
              <span>{when(row.created_at)}</span>
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}

function FiscalCardView({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const t = useT();
  const { can } = useCan();
  const card = useFiscalCard(uuid);
  const retry = useRetryFiscal();
  const [retrying, setRetrying] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  if (card.isError) return <InlineError title={t('pos_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const receipt = card.data;

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">
            {receipt.store?.name ?? '—'} · {money(receipt.total)}
          </h2>
          <Pill tone={FISCAL_TONE[receipt.status] ?? 'neutral'}>{t(fiscalLabel(receipt.status))}</Pill>
        </div>
        <Facts
          rows={[
            [t('pos_branch'), receipt.branch?.name ?? '—'],
            [t('pos_fis_type'), receipt.type],
            [t('pos_fis_provider'), receipt.provider ? `${receipt.driver} · ${receipt.provider}` : receipt.driver],
            [t('pos_fis_paymentForm'), receipt.payment_form],
            [t('pos_fis_attemptsLabel'), String(receipt.attempts)],
            [t('pos_fis_nextAttempt'), when(receipt.next_attempt_at)],
            [t('pos_fis_createdAt'), when(receipt.created_at)],
            [t('pos_fis_issuedAt'), when(receipt.issued_at)],
            ...(receipt.fiscal_sign ? ([[t('pos_fis_sign'), receipt.fiscal_sign]] as const) : []),
            ...(receipt.receipt_number ? ([[t('pos_fis_number'), receipt.receipt_number]] as const) : []),
          ]}
        />
      </Card>
      {(receipt.full_error || receipt.error) && (
        <Card>
          <h3 className="mb-1 text-body font-bold text-fg">{t('pos_fis_reason')}</h3>
          <p className="whitespace-pre-wrap break-words text-body text-fg">{receipt.full_error ?? receipt.error}</p>
        </Card>
      )}
      <Section title={t('pos_fis_lines')}>
        <Card padded={false}>
          <ul aria-label={t('pos_fis_lines')} className="divide-y divide-line">
            {receipt.lines.map((line, index) => (
              <li key={index} className="flex items-center justify-between gap-3 p-3 text-body">
                <span className="min-w-0 break-words">
                  {line.name ?? '—'}
                  {line.mxik_code && <span className="text-footnote text-fg-muted"> · MXIK {line.mxik_code}</span>}
                </span>
                <span className="shrink-0 font-bold">{line.amount ? money(line.amount) : '—'}</span>
              </li>
            ))}
            {receipt.lines.length === 0 && <li className="p-3 text-footnote text-fg-muted">{t('pos_fis_noLines')}</li>}
          </ul>
        </Card>
      </Section>
      {receipt.retryable ? (
        can('stores.manage') && (
          <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
            <Button onClick={() => setRetrying(true)}>{t('pos_fis_retry')}</Button>
          </div>
        )
      ) : (
        receipt.status !== 'issued' && <p className="text-footnote text-fg-muted">{t('pos_fis_externalText')}</p>
      )}
      {retrying && (
        <ReasonDialog
          title={t('pos_fis_retryTitle')}
          text={t('pos_fis_retryText')}
          submitLabel={t('pos_fis_retryConfirm')}
          busy={retry.isPending}
          error={retry.error ? errorText(retry.error, t) : null}
          reasonError={fieldError(retry.error, 'reason')}
          onClose={() => setRetrying(false)}
          onSubmit={(reason) =>
            retry.mutate(
              { uuid: receipt.uuid, reason },
              {
                onSuccess: (result) => {
                  setRetrying(false);
                  setNotice(result.status === 'issued' ? t('pos_fis_retryIssued') : t('pos_fis_retryStill', { error: result.error ?? t('pos_fis_noAnswer') }));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
