'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { BookOpen, Scale, Search } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Dialog, EmptyState, Field, fieldClass, InlineError, Pill, Tabs } from '@rizqim/ui';
import { BALANCE_FILTERS, useBalances, useLedger, type BalanceFilter, type BalanceRow } from '@/lib/api/finance';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { BALANCE_FILTER_LABEL, ledgerTypeLabel, signed, uzs } from './financeModel';
import { CHIP, chipTone } from './PayoutsPanel';
import { Row } from './ReasonDialog';

// Balans va qarzlar: every store's wallet and debt, split into the subscription part and the commission part (only the
// latter counts against the credit limit). The over-limit list is the stores whose Mini App orders are refused now.
export function BalancesPanel({ enabled }: { enabled: boolean }) {
  const t = useT();
  const [filter, setFilter] = useState<BalanceFilter>('all');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [ledgerOf, setLedgerOf] = useState<BalanceRow['store'] | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      setQ(search);
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const balances = useBalances(filter, q, page, enabled);
  const totals = balances.data?.totals;
  const pages = balances.data ? Math.max(1, Math.ceil(balances.data.total / balances.data.per_page)) : 1;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      {totals && (
        <Card>
          <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
            <Row label={t('finance_totalsWallet')} value={uzs(totals.wallet_total)} strong />
            <Row label={t('finance_totalsDebt')} value={uzs(totals.debt_total)} strong />
            <Row label={t('finance_totalsSubscription')} value={uzs(totals.subscription_debt_total)} />
            <Row label={t('finance_totalsCommission')} value={uzs(totals.commission_debt_total)} />
          </dl>
          <p className="mt-2 text-caption text-fg-muted">{t('finance_limitHint')}</p>
        </Card>
      )}
      <div role="group" aria-label={t('finance_balanceFilter')} className="flex flex-wrap gap-2">
        {BALANCE_FILTERS.map((value) => (
          <button
            key={value}
            type="button"
            aria-pressed={filter === value}
            className={cx(CHIP, chipTone(filter === value))}
            onClick={() => {
              setFilter(value);
              setPage(1);
            }}
          >
            {value === 'over_limit' && totals ? t('finance_tabCount', { label: t(BALANCE_FILTER_LABEL[value]), count: totals.over_limit }) : t(BALANCE_FILTER_LABEL[value])}
          </button>
        ))}
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('finance_balanceSearch')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
      </div>
      {balances.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(balances.error, t)} retryLabel={t('retry')} retrying={balances.isFetching} onRetry={() => void balances.refetch()} />
      ) : !balances.data ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : balances.data.data.length === 0 ? (
        <EmptyState icon={Scale} title={t('finance_balancesEmpty')} />
      ) : (
        <>
          <ul aria-label={t('finance_balancesLabel')} className="flex flex-col gap-2">
            {balances.data.data.map((row) => (
              <li key={row.store.id}>
                <Card className="flex flex-col gap-2">
                  <span className="flex flex-wrap items-center gap-2">
                    <Link href={`/stores/${row.store.id}`} className="break-words text-body font-bold text-fg hover:underline">
                      {row.store.name}
                    </Link>
                    {row.over_limit && <Pill tone="danger">{t('finance_overLimit')}</Pill>}
                    {row.is_order_locked && <Pill tone="warning">{t('finance_orderLocked')}</Pill>}
                  </span>
                  <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                    <Row label={t('finance_wallet')} value={uzs(row.wallet_balance)} />
                    <Row label={t('finance_debt')} value={uzs(row.debt_balance)} strong />
                    <Row label={t('finance_subscriptionDebt')} value={uzs(row.subscription_debt)} />
                    <Row label={t('finance_commissionDebt')} value={uzs(row.commission_debt)} />
                    <Row label={t('finance_creditLimit')} value={uzs(row.credit_limit.replace(/^-/, ''))} />
                  </dl>
                  <span className="flex flex-wrap items-center gap-2">
                    {row.pending_payouts !== '0.00' && <span className="text-footnote text-fg-muted">{t('finance_pendingPayouts', { amount: uzs(row.pending_payouts) })}</span>}
                    <Button variant="ghost" icon={BookOpen} onClick={() => setLedgerOf(row.store)}>
                      {t('finance_ledger')}
                    </Button>
                  </span>
                </Card>
              </li>
            ))}
          </ul>
          {pages > 1 && (
            <div className="flex flex-wrap items-center gap-2">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>
                {t('finance_prev')}
              </Button>
              <span className="text-footnote text-fg-muted">{t('finance_page', { page, total: balances.data.total })}</span>
              <Button variant="secondary" disabled={page >= pages} onClick={() => setPage((value) => value + 1)}>
                {t('finance_next')}
              </Button>
            </div>
          )}
        </>
      )}
      {ledgerOf && <LedgerDialog store={ledgerOf} onClose={() => setLedgerOf(null)} />}
    </div>
  );
}

function LedgerDialog({ store, onClose }: { store: { id: number; name: string }; onClose: () => void }) {
  const t = useT();
  const [ledger, setLedger] = useState<'debt' | 'wallet'>('debt');
  const rows = useLedger(store.id, ledger);
  const list = useMemo(() => rows.data?.pages.flatMap((page) => page.data) ?? [], [rows.data]);
  return (
    <Dialog open title={t('finance_ledgerTitle', { store: store.name })} closeLabel={t('close')} onClose={onClose}>
      <Tabs
        idPrefix="finance-ledger"
        label={t('finance_ledger')}
        value={ledger}
        onChange={setLedger}
        tabs={[
          { id: 'debt', label: t('finance_ledgerDebt') },
          { id: 'wallet', label: t('finance_ledgerWallet') },
        ]}
      />
      <div role="tabpanel" id={`finance-ledger-panel-${ledger}`} aria-labelledby={`finance-ledger-tab-${ledger}`}>
        {rows.isError ? (
          <InlineError title={t('finance_loadError')} message={errorText(rows.error, t)} retryLabel={t('retry')} onRetry={() => void rows.refetch()} />
        ) : !rows.isSuccess ? (
          <CardSkeletons count={3} lines={2} label={t('loading')} />
        ) : list.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('finance_ledgerEmpty')}</p>
        ) : (
          <ul className="flex flex-col divide-y divide-line">
            {list.map((row) => {
              const type = ledgerTypeLabel(row.type);
              return (
                <li key={row.id} className="flex flex-col gap-0.5 py-2">
                  <span className="flex flex-wrap items-baseline justify-between gap-2">
                    <span className="text-footnote font-bold text-fg">{type ? t(type) : row.type}</span>
                    <span className="text-footnote font-bold text-fg">{signed(row.amount)}</span>
                  </span>
                  <span className="text-caption text-fg-muted">{`${when(row.created_at)} · ${uzs(row.balance_before)} → ${uzs(row.balance_after)}`}</span>
                  {row.subscription_invoice && <span className="text-caption text-fg">{t('finance_ledgerInvoice', { number: row.subscription_invoice.number, period: row.subscription_invoice.period })}</span>}
                  {row.payout_request_id !== null && <span className="text-caption text-fg">{t('finance_ledgerPayout', { id: row.payout_request_id })}</span>}
                  {row.order && <span className="text-caption text-fg">{t('finance_ledgerOrder', { code: `#${row.order.short_code ?? row.order.uuid.slice(0, 8)}` })}</span>}
                  {row.notes && <span className="break-words text-caption text-fg-muted">{row.notes}</span>}
                </li>
              );
            })}
          </ul>
        )}
        {rows.hasNextPage && (
          <Button variant="secondary" fullWidth loading={rows.isFetchingNextPage} onClick={() => void rows.fetchNextPage()}>
            {t('finance_loadMore')}
          </Button>
        )}
      </div>
    </Dialog>
  );
}
