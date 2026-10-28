'use client';

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { RefreshCw, ShieldAlert, UsersRound } from 'lucide-react';
import { Button, Card, cx, EmptyState, IconButton, Notice, PageHeader, Section, Tabs } from '@rizqim/ui';
import { FINANCE_KEYS, useAdjustments, useFinanceSummary, usePayouts } from '@/lib/api/finance';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { AdjustmentDialog, AdjustmentsPanel } from './AdjustmentsPanel';
import { BalancesPanel } from './BalancesPanel';
import { FINANCE_TABS, TAB_LABEL, uzs, type FinanceTab } from './financeModel';
import { PayoutDialog, PayoutsPanel } from './PayoutsPanel';
import { ReportPanel } from './ReportPanel';
import { SettlementsPanel } from './SettlementsPanel';
import { TopupsPanel } from './TopupsPanel';

export interface FinanceInitial {
  tab: FinanceTab;
  payout: number | null;
  adjustment: number | null;
  topup: string;
}

// Moliya (P4-23, docs/PHASE4_PLAN.md 4.5): six tabs, the second-approval queue on top. Links from Bugun open a tab and a
// record (?tab=, ?payout=, ?adjustment=, ?topup=). Every action asks for a reason; the shell asks for the step-up.
export function FinanceView({ initial }: { initial: FinanceInitial }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('finance.view');
  const client = useQueryClient();
  const [tab, setTab] = useState<FinanceTab>(initial.tab);
  const [payoutId, setPayoutId] = useState<number | null>(initial.payout);
  const [adjustmentId, setAdjustmentId] = useState<number | null>(initial.adjustment);
  const [notice, setNotice] = useState<string | null>(null);
  const summary = useFinanceSummary(allowed);
  const secondPayouts = usePayouts('awaiting_second', allowed && can('payouts.approve'));
  const pendingAdjustments = useAdjustments('pending', allowed && can('finance.manage'));

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('finance_title')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const choose = (next: FinanceTab) => {
    setTab(next);
    if (typeof window !== 'undefined') window.history.replaceState(null, '', `/finance?tab=${next}`);
  };
  const done = (message: string) => setNotice(message);
  const counts: Partial<Record<FinanceTab, number>> = summary.data
    ? {
        payouts: summary.data.payouts.pending + summary.data.payouts.awaiting_second,
        topups: summary.data.topups.stuck,
        settlements: summary.data.settlement_failures.count,
        balances: summary.data.balances.over_limit,
        adjustments: summary.data.adjustments.pending,
      }
    : {};
  const queue = [
    ...(secondPayouts.data?.pages.flatMap((page) => page.data) ?? []).map((row) => ({
      key: `p-${row.id}`,
      text: t('finance_queuePayout', { store: row.store?.name ?? '—', amount: uzs(row.amount), name: row.first_approval?.by?.name ?? '—' }),
      open: () => setPayoutId(row.id),
    })),
    ...(pendingAdjustments.data?.pages.flatMap((page) => page.data) ?? []).map((row) => ({
      key: `a-${row.id}`,
      text: t('finance_queueAdjustment', { store: row.store?.name ?? '—', amount: uzs(row.amount), name: row.requested_by?.name ?? '—' }),
      open: () => setAdjustmentId(row.id),
    })),
  ];

  return (
    <>
      <PageHeader
        title={t('finance_title')}
        subtitle={t('finance_subtitle')}
        action={<IconButton icon={RefreshCw} label={t('finance_refresh')} onClick={() => void client.invalidateQueries({ queryKey: FINANCE_KEYS.all })} className={cx(summary.isFetching && 'animate-spin motion-reduce:animate-none')} />}
      />
      <Notice message={notice} />
      {summary.data && <p className="mb-4 max-w-5xl text-footnote text-fg-muted">{t('finance_threshold', { amount: uzs(summary.data.threshold) })}</p>}

      {queue.length > 0 && (
        <Section title={t('finance_queueTitle')} className="mb-4 max-w-5xl">
          <Card padded={false}>
            <p className="px-4 pt-3 text-caption text-fg-muted">{t('finance_queueText')}</p>
            <ul className="divide-y divide-line">
              {queue.map((item) => (
                <li key={item.key} className="flex flex-wrap items-center gap-3 px-4 py-3">
                  <UsersRound aria-hidden="true" className="size-4 shrink-0 text-info-soft-fg" />
                  <span className="min-w-0 flex-1 break-words text-footnote text-fg">{item.text}</span>
                  <Button variant="secondary" onClick={item.open}>
                    {t('finance_queueOpen')}
                  </Button>
                </li>
              ))}
            </ul>
          </Card>
        </Section>
      )}

      <Tabs
        idPrefix="finance"
        label={t('finance_tabs')}
        value={tab}
        onChange={choose}
        tabs={FINANCE_TABS.map((id) => ({ id, label: counts[id] ? t('finance_tabCount', { label: t(TAB_LABEL[id]), count: counts[id] }) : t(TAB_LABEL[id]) }))}
      />
      <div role="tabpanel" id={`finance-panel-${tab}`} aria-labelledby={`finance-tab-${tab}`}>
        {tab === 'payouts' && <PayoutsPanel enabled={allowed} onOpen={setPayoutId} />}
        {tab === 'topups' && <TopupsPanel enabled={allowed} highlight={initial.topup} />}
        {tab === 'settlements' && <SettlementsPanel enabled={allowed} canRetry={can('finance.manage')} onDone={done} />}
        {tab === 'balances' && <BalancesPanel enabled={allowed} />}
        {tab === 'adjustments' && <AdjustmentsPanel enabled={allowed} canManage={can('finance.manage')} threshold={summary.data?.threshold} onOpen={setAdjustmentId} onDone={done} />}
        {tab === 'report' && <ReportPanel enabled={allowed} canExport={can('export')} onDone={done} />}
      </div>

      {payoutId !== null && <PayoutDialog key={payoutId} id={payoutId} onClose={() => setPayoutId(null)} onDone={done} />}
      {adjustmentId !== null && <AdjustmentDialog key={adjustmentId} id={adjustmentId} onClose={() => setAdjustmentId(null)} onDone={done} />}
    </>
  );
}
