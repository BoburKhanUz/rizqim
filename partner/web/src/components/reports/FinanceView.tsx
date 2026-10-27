'use client';

import { useState } from 'react';
import { ArrowDownToLine, Banknote, Wallet } from 'lucide-react';
import { Button, Card, InlineError, Skeleton, cx, formatMoney, formatNumber, PageHeader } from '@rizqim/ui';
import { getApiErrorStatus, useOwnerFinance, type OwnerFinance } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { PayoutDialog, TopupDialog } from './FinanceDialogs';
import { PaymentsPanel, PayoutsPanel, TransactionsPanel } from './HistoryPanels';
import { PlanPanel } from './PlanPanel';

const TABS = ['ledger', 'wallet', 'payments', 'payouts', 'plan'] as const;
type Tab = (typeof TABS)[number];

// Moliya: the balances (owner), the money history, online payments, payout requests, the tariff plan and its invoices.
// Every figure and every rule is the server's: a refused payout shows the server's own message.
export function FinanceView() {
  const { t } = useI18n();
  const [tab, setTab] = useState<Tab>('ledger');

  return (
    <>
      <PageHeader title={t('fin_title')} subtitle={t('fin_subtitle')} />
      <div className="flex flex-col gap-6">
        <BalanceSection />
        <div className="flex flex-col gap-4">
          <div role="tablist" aria-label={t('fin_tabs')} className="flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1">
            {TABS.map((id) => (
              <button
                key={id}
                type="button"
                role="tab"
                id={`fin-tab-${id}`}
                aria-selected={tab === id}
                aria-controls={`fin-panel-${id}`}
                onClick={() => setTab(id)}
                className={cx(
                  'min-h-11 shrink-0 cursor-pointer rounded-control px-4 text-body font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:outline-brand',
                  tab === id ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:text-fg',
                )}
              >
                {t(`fin_tab_${id}` as const)}
              </button>
            ))}
          </div>
          <div role="tabpanel" id={`fin-panel-${tab}`} aria-labelledby={`fin-tab-${tab}`}>
            {tab === 'ledger' && <TransactionsPanel kind="ledger" />}
            {tab === 'wallet' && <TransactionsPanel kind="wallet" />}
            {tab === 'payments' && <PaymentsPanel />}
            {tab === 'payouts' && <PayoutsPanel />}
            {tab === 'plan' && <PlanPanel />}
          </div>
        </div>
      </div>
    </>
  );
}

// GET /owner/finance is the owner's alone: anyone else with finance.view still has the history below
function BalanceSection() {
  const { t } = useI18n();
  const finance = useOwnerFinance();
  const [dialog, setDialog] = useState<'payout' | 'topup' | null>(null);

  if (finance.isPending) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2" role="status" aria-busy="true">
        <Skeleton className="h-28 w-full" />
        <Skeleton className="h-28 w-full" />
      </div>
    );
  }
  if (finance.isError) {
    if (getApiErrorStatus(finance.error) === 403) {
      return (
        <Card>
          <p className="text-body text-fg-muted">{t('fin_ownerOnly')}</p>
        </Card>
      );
    }
    return <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={finance.isFetching} onRetry={() => void finance.refetch()} />;
  }

  const data = finance.data;
  return (
    <>
      <Balances data={data} onPayout={() => setDialog('payout')} onTopup={() => setDialog('topup')} />
      <PayoutDialog open={dialog === 'payout'} onClose={() => setDialog(null)} minReserve={data.min_payout_reserve} />
      <TopupDialog open={dialog === 'topup'} onClose={() => setDialog(null)} />
    </>
  );
}

function Balances({ data, onPayout, onTopup }: { data: OwnerFinance; onPayout: () => void; onTopup: () => void }) {
  const { t, language } = useI18n();
  return (
    <div className="flex flex-col gap-4">
      {data.is_order_locked && (
        <p role="alert" className="rounded-card bg-danger-soft px-4 py-3 text-body font-bold text-danger-soft-fg">
          {t('fin_locked')}
        </p>
      )}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card className="flex flex-col gap-1">
          <p className="flex items-center gap-2 text-footnote font-bold text-fg-muted">
            <Wallet aria-hidden="true" className="size-4" />
            {t('fin_wallet')}
          </p>
          <p className="text-display font-extrabold text-brand">{formatMoney(data.wallet_balance, language)}</p>
          <p className="text-caption text-fg-muted">{t('fin_walletNote')}</p>
          <p className="text-caption text-fg-muted">
            {t('fin_minReserve')}: {formatMoney(data.min_payout_reserve, language)}
          </p>
          <div className="mt-2">
            <Button icon={ArrowDownToLine} onClick={onPayout}>
              {t('fin_payout')}
            </Button>
          </div>
        </Card>
        <Card className="flex flex-col gap-1">
          <p className="flex items-center gap-2 text-footnote font-bold text-fg-muted">
            <Banknote aria-hidden="true" className="size-4" />
            {t('fin_debt')}
          </p>
          <p className="text-display font-extrabold text-fg">{formatMoney(data.debt_balance, language)}</p>
          <p className="text-caption text-fg-muted">{t('fin_debtNote')}</p>
          <p className="text-caption text-fg-muted">
            {t('fin_creditLimit')}: {formatMoney(data.credit_limit, language)} · {t('fin_commissionRate')}: {formatNumber(data.commission_rate, language)}%
          </p>
          <div className="mt-2">
            <Button variant="secondary" onClick={onTopup}>
              {t('fin_topup')}
            </Button>
          </div>
        </Card>
      </div>
    </div>
  );
}
