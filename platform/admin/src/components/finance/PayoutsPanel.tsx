'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { ChevronRight, HandCoins } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Dialog, EmptyState, FormError, InlineError, Pill } from '@rizqim/ui';
import { idempotencyKey } from '@/lib/api/orders';
import { PAYOUT_FILTERS, useDecidePayout, usePayout, usePayouts, type PayoutCard, type PayoutFilter, type PayoutRow } from '@/lib/api/finance';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { ExportButton } from '../ExportButton';
import { useT } from '../I18nProvider';
import { PAYOUT_FILTER_LABEL, payoutTone, uzs } from './financeModel';
import { reasonOk, ReasonFields, Row } from './ReasonDialog';

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
export const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');
export { CHIP };

// Payoutlar: the stores' payout requests. Open ones first (oldest first); a card shows "Hisoblangan — Ushlangan qarz —
// O'tkaziladi" and the approve / reject actions.
export function PayoutsPanel({ enabled, onOpen }: { enabled: boolean; onOpen: (id: number) => void }) {
  const t = useT();
  const [filter, setFilter] = useState<PayoutFilter>('open');
  const payouts = usePayouts(filter, enabled);
  const rows = useMemo(() => payouts.data?.pages.flatMap((page) => page.data) ?? [], [payouts.data]);
  const counts = payouts.data?.pages[0]?.counts;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div role="group" aria-label={t('finance_payoutFilter')} className="flex flex-wrap gap-2">
        {PAYOUT_FILTERS.map((value) => {
          const count = value === 'pending' ? counts?.pending : value === 'awaiting_second' ? counts?.awaiting_second : value === 'open' && counts ? counts.pending + counts.awaiting_second : undefined;
          const label = t(PAYOUT_FILTER_LABEL[value]);
          return (
            <button key={value} type="button" aria-pressed={filter === value} className={cx(CHIP, chipTone(filter === value))} onClick={() => setFilter(value)}>
              {count === undefined ? label : t('finance_tabCount', { label, count })}
            </button>
          );
        })}
        <ExportButton list="payouts" query={{ status: filter }} className="sm:ml-auto" />
      </div>
      {payouts.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(payouts.error, t)} retryLabel={t('retry')} retrying={payouts.isFetching} onRetry={() => void payouts.refetch()} />
      ) : !payouts.isSuccess ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={HandCoins} title={t('finance_payoutsEmpty')} />
      ) : (
        <>
          <ul aria-label={t('finance_payoutsLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <PayoutListRow key={row.id} row={row} onOpen={() => onOpen(row.id)} />
            ))}
          </ul>
          {payouts.hasNextPage && (
            <Button variant="secondary" loading={payouts.isFetchingNextPage} onClick={() => void payouts.fetchNextPage()}>
              {t('finance_loadMore')}
            </Button>
          )}
        </>
      )}
    </div>
  );
}

function PayoutListRow({ row, onOpen }: { row: PayoutRow; onOpen: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onOpen}
          aria-label={t('finance_payoutOpen', { id: row.id })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="flex flex-wrap items-center gap-2">
              <span className="text-body font-bold text-fg">{uzs(row.amount)}</span>
              <Pill tone={payoutTone(row.status)}>{row.status_label}</Pill>
              {row.needs_second_approval && row.status !== 'completed' && row.status !== 'rejected' && <Pill tone="info">{t('finance_payoutNeedsTwo')}</Pill>}
            </span>
            <span className="break-words text-footnote text-fg">{row.store?.name ?? '—'}</span>
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span className="font-mono">{row.card}</span>
              <span>{when(row.created_at)}</span>
              {row.first_approval && <span>{t('finance_payoutFirst', { name: row.first_approval.by?.name ?? '—', time: when(row.first_approval.at) })}</span>}
              {row.netted_amount !== null && row.netted_amount !== '0.00' && <span>{`${t('finance_netted')}: ${uzs(row.netted_amount)}`}</span>}
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}

// The payout's card: the breakdown, the store's balances, and the decision (one dialog: the card, then the reason form)
export function PayoutDialog({ id, onClose, onDone }: { id: number; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const payout = usePayout(id);
  const decide = useDecidePayout();
  const [mode, setMode] = useState<'view' | 'approve' | 'reject'>('view');
  const [reason, setReason] = useState('');
  const [key, setKey] = useState(idempotencyKey);
  const card = payout.data;

  const start = (next: 'approve' | 'reject') => {
    decide.reset();
    setReason('');
    setKey(idempotencyKey());
    setMode(next);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!card || mode === 'view' || decide.isPending || !reasonOk(reason)) return;
    decide.mutate(
      { id: card.id, decision: mode, reason: reason.trim(), key },
      {
        onSuccess: (result) => {
          setMode('view');
          if (result.status === 'awaiting_second') onDone(t('finance_payoutFirstDone'));
          else if (result.status === 'completed') onDone(t('finance_payoutApproved', { transfer: uzs(result.breakdown?.transfer ?? result.amount), netted: uzs(result.breakdown?.netted ?? '0') }));
          else onDone(t('finance_payoutRejected'));
        },
      },
    );
  };

  const title = mode === 'approve' ? t('finance_approveTitle') : mode === 'reject' ? t('finance_rejectTitle') : t('finance_payoutTitle', { id });
  const approveText =
    card && mode === 'approve'
      ? card.actions.approve_completes
        ? t('finance_approveText', { transfer: uzs(card.breakdown?.transfer ?? card.amount), netted: uzs(card.breakdown?.netted ?? '0') })
        : t('finance_approveFirstText')
      : mode === 'reject'
        ? t('finance_rejectText')
        : undefined;

  return (
    <Dialog open title={title} description={approveText} busy={decide.isPending} closeLabel={t('close')} onClose={mode === 'view' ? onClose : () => setMode('view')}>
      {payout.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(payout.error, t)} retryLabel={t('retry')} retrying={payout.isFetching} onRetry={() => void payout.refetch()} />
      ) : !card ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : mode === 'view' ? (
        <PayoutDetails card={card} onApprove={() => start('approve')} onReject={() => start('reject')} />
      ) : (
        <form onSubmit={submit} className="flex flex-col gap-4">
          {mode === 'approve' && card.breakdown && <Breakdown card={card} />}
          <ReasonFields reason={reason} onChange={setReason} error={fieldError(decide.error, 'reason')} />
          <FormError message={decide.error ? errorText(decide.error, t) : null} />
          <div className="flex gap-2 [&>*]:flex-1">
            <Button variant="secondary" onClick={() => setMode('view')} disabled={decide.isPending}>
              {t('cancel')}
            </Button>
            <Button type="submit" variant={mode === 'reject' ? 'danger' : 'primary'} loading={decide.isPending} disabled={!reasonOk(reason)}>
              {mode === 'reject' ? t('finance_reject') : card.actions.approve_completes ? t('finance_approve') : t('finance_approveFirst')}
            </Button>
          </div>
        </form>
      )}
    </Dialog>
  );
}

function Breakdown({ card }: { card: PayoutCard }) {
  const t = useT();
  const breakdown = card.breakdown;
  if (!breakdown) return null;
  return (
    <section aria-label={t('finance_breakdown')} className="rounded-control border border-line p-3">
      <dl className="divide-y divide-line">
        <Row label={t('finance_accrued')} value={uzs(breakdown.accrued)} />
        <Row label={t('finance_netted')} value={breakdown.netted === '0.00' ? uzs('0') : `−${uzs(breakdown.netted)}`} />
        {breakdown.lines.map((line, index) => (
          <Row key={`${line.kind}-${line.invoice?.id ?? index}`} label={<span className="pl-3">{line.label}</span>} value={uzs(line.amount)} />
        ))}
        <Row label={breakdown.preview ? t('finance_transfer') : t('finance_transferred')} value={uzs(breakdown.transfer)} strong />
      </dl>
      <p className="mt-2 text-caption text-fg-muted">{breakdown.lines.length === 0 ? t('finance_nettingNone') : breakdown.preview ? t('finance_preview') : null}</p>
    </section>
  );
}

function PayoutDetails({ card, onApprove, onReject }: { card: PayoutCard; onApprove: () => void; onReject: () => void }) {
  const t = useT();
  const waitingForOther = card.status === 'awaiting_second' && !card.actions.can_approve && card.actions.can_reject;
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <Pill tone={payoutTone(card.status)}>{card.status_label}</Pill>
        {card.needs_second_approval && card.status !== 'completed' && card.status !== 'rejected' && <Pill tone="info">{t('finance_payoutNeedsTwo')}</Pill>}
      </div>
      <dl className="divide-y divide-line">
        <Row label={t('finance_payoutStore')} value={card.store?.name ?? '—'} />
        <Row label={t('finance_payoutCard')} value={<span className="font-mono">{card.card}</span>} />
        <Row label={t('finance_payoutCreated')} value={when(card.created_at)} />
        {card.notes && <Row label={t('finance_payoutNotes')} value={card.notes} />}
        {card.resolution_notes && <Row label={t('finance_payoutResolution')} value={card.resolution_notes} />}
      </dl>
      <Breakdown card={card} />
      {card.balances && (
        <section aria-label={t('finance_balancesNow')}>
          <h3 className="mb-1 text-footnote font-bold text-fg-muted">{t('finance_balancesNow')}</h3>
          <dl className="divide-y divide-line">
            <Row label={t('finance_wallet')} value={uzs(card.balances.wallet)} />
            <Row label={t('finance_debt')} value={uzs(card.balances.debt)} />
            <Row label={t('finance_subscriptionDebt')} value={uzs(card.balances.subscription_debt)} />
            <Row label={t('finance_commissionDebt')} value={uzs(card.balances.commission_debt)} />
          </dl>
        </section>
      )}
      {card.first_approval && <p className="text-footnote text-fg-muted">{t('finance_payoutFirst', { name: card.first_approval.by?.name ?? '—', time: when(card.first_approval.at) })}</p>}
      {card.processed_by && <p className="text-footnote text-fg-muted">{t('finance_payoutProcessed', { name: card.processed_by.name, time: when(card.processed_at) })}</p>}
      {waitingForOther && <p className="text-footnote font-bold text-info-soft-fg">{t('finance_payoutWaitOther')}</p>}
      {(card.actions.can_approve || card.actions.can_reject) && (
        <div className="flex gap-2 [&>*]:flex-1">
          {card.actions.can_reject && (
            <Button variant="secondary" onClick={onReject}>
              {t('finance_reject')}
            </Button>
          )}
          {card.actions.can_approve && <Button onClick={onApprove}>{card.actions.approve_completes ? t('finance_approve') : t('finance_approveFirst')}</Button>}
        </div>
      )}
    </div>
  );
}
