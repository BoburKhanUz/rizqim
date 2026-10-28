'use client';

import Link from 'next/link';
import { useState } from 'react';
import { Check, ExternalLink, X } from 'lucide-react';
import { Button, Card, CardSkeletons, InlineError, Notice, Pill, Section } from '@rizqim/ui';
import { useCatalog, useStoreBilling, type StoreBilling } from '@/lib/api/pos';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CHANNEL_LABEL, daysUntil, day, INVOICE_TONE, invoiceLabel, money, percentText, SUBSCRIPTION_LABEL, SUBSCRIPTION_TONE } from './posModel';
import { featureLabel } from './featureLabels';
import { BackButton, Facts } from './PosParts';
import { BillingDialog, type BillingDialogKind } from './BillingDialogs';

// A store's subscription (P4-24): the plan and period, the trial, what the plan allows (features and devices), the debt split
// (the credit limit counts the commission part only), the commission per channel, the history and the last invoices. The actions
// are P4-21's: each asks for a reason and the shell asks for the step-up. Nothing here lets anyone act as the store's owner.

export function StoreBillingPanel({ storeId, onBack, onOpenInvoice }: { storeId: number; onBack: () => void; onOpenInvoice?: (uuid: string) => void }) {
  const t = useT();
  const { can } = useCan();
  const billing = useStoreBilling(storeId);
  const [dialog, setDialog] = useState<BillingDialogKind | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (billing.isError) return <InlineError title={t('pos_loadError')} message={errorText(billing.error, t)} retryLabel={t('retry')} retrying={billing.isFetching} onRetry={() => void billing.refetch()} />;
  if (!billing.data) return <CardSkeletons count={3} lines={4} label={t('loading')} />;

  const data = billing.data;
  const manage = can('subscriptions.manage');
  const subscription = data.subscription;
  const trialDays = subscription?.status === 'trialing' ? daysUntil(subscription.trial_ends_at) : null;

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />

      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">{data.store.name}</h2>
          {subscription ? <Pill tone={SUBSCRIPTION_TONE[subscription.status]}>{t(SUBSCRIPTION_LABEL[subscription.status])}</Pill> : <Pill tone="neutral">{t('pos_store_noSubscription')}</Pill>}
          {data.entitlements.settings_locked && <Pill tone="danger">{t('pos_store_settingsLocked')}</Pill>}
          <Link href={`/stores/${data.store.id}`} className="ml-auto inline-flex min-h-11 items-center gap-1.5 rounded-control px-2 text-footnote font-bold text-brand hover:bg-surface-2">
            <ExternalLink aria-hidden="true" className="size-4" />
            {t('pos_store_openCard')}
          </Link>
        </div>
        {subscription ? (
          <Facts
            label={t('pos_store_subscription')}
            rows={[
              [t('pos_plan'), `${subscription.plan.name}${subscription.plan.version < 2 ? ` (${t('pos_store_oldPlan')})` : ''}`],
              [t('pos_period'), t(subscription.billing_period === 'yearly' ? 'pos_period_yearly' : 'pos_period_monthly')],
              [t('pos_store_currentPeriod'), `${when(subscription.current_period.start)} — ${when(subscription.current_period.end)}`],
              ...(subscription.trial_ends_at ? ([[t('pos_store_trialEnds'), `${when(subscription.trial_ends_at)}${trialDays !== null && trialDays >= 0 ? ` · ${t('pos_sub_trialDaysLeft', { days: trialDays })}` : ''}`]] as const) : []),
              ...(subscription.term_ends_at ? ([[t('pos_store_termEnds'), when(subscription.term_ends_at)]] as const) : []),
              ...(subscription.next_plan || subscription.next_billing_period
                ? ([
                    [
                      t('pos_store_change'),
                      t('pos_store_changeText', {
                        plan: subscription.next_plan?.name ?? subscription.plan.name,
                        period: t((subscription.next_billing_period ?? subscription.billing_period) === 'yearly' ? 'pos_period_yearly' : 'pos_period_monthly'),
                        date: when(subscription.changes_at),
                      }),
                    ],
                  ] as const)
                : []),
              ...(subscription.cancel_at_period_end ? ([[t('pos_store_cancelling'), when(subscription.changes_at)]] as const) : []),
              ...(subscription.locked_at ? ([[t('pos_store_lockedAt'), when(subscription.locked_at)]] as const) : []),
              ...(subscription.price_locked_until ? ([[t('pos_store_priceLocked'), when(subscription.price_locked_until)]] as const) : []),
            ]}
          />
        ) : (
          <p className="text-footnote text-fg-muted">{t('pos_store_noSubscriptionText')}</p>
        )}
        {data.entitlements.notice && <p className="mt-3 break-words rounded-control bg-warning-soft p-3 text-footnote text-warning-soft-fg">{data.entitlements.notice.message}</p>}
      </Card>

      {manage && (
        <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
          <Button onClick={() => setDialog('assign')}>{t('pos_store_assign')}</Button>
          {subscription && (
            <>
              <Button variant="secondary" onClick={() => setDialog('period')}>
                {t('pos_store_changePeriod')}
              </Button>
              {subscription.status === 'trialing' && (
                <Button variant="secondary" onClick={() => setDialog('trial')}>
                  {t('pos_store_extendTrial')}
                </Button>
              )}
              <Button variant="danger" onClick={() => setDialog('cancel')}>
                {t('pos_store_cancel')}
              </Button>
            </>
          )}
          <Button variant="secondary" onClick={() => setDialog('commission')}>
            {t('pos_store_commissionEdit')}
          </Button>
        </div>
      )}

      <Section title={t('pos_store_features')}>
        <Card>
          <FeatureList data={data} />
        </Card>
      </Section>

      <Section title={t('pos_store_devices')}>
        <Card padded={false}>
          <ul aria-label={t('pos_store_devices')} className="divide-y divide-line">
            {Object.entries(data.entitlements.devices).map(([type, allowance]) => (
              <li key={type} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                <span className="font-bold">{t(type === 'pos' ? 'pos_store_dev_pos' : type === 'kds' ? 'pos_store_dev_kds' : 'pos_store_dev_waiter')}</span>
                <span className="text-footnote text-fg-muted">
                  {allowance.unlimited
                    ? t('pos_store_dev_unlimited')
                    : t('pos_store_dev_usage', { active: allowance.active ?? 0, included: allowance.included ?? '∞' })}
                  {allowance.extra ? ` · ${t('pos_store_dev_extra', { count: allowance.extra, price: money(allowance.extra_price) })}` : ''}
                </span>
              </li>
            ))}
          </ul>
        </Card>
      </Section>

      <Section title={t('pos_store_debt')}>
        <Card>
          <Facts
            rows={[
              [t('pos_debt_total'), money(data.debt.debt_balance)],
              [t('pos_debt_subscription'), money(data.debt.subscription_debt)],
              [t('pos_debt_commission'), money(data.debt.commission_debt)],
              [t('pos_debt_limit'), money(data.debt.credit_limit)],
            ]}
          />
          {data.debt.over_credit_limit && <p className="mt-2 text-footnote font-bold text-danger-soft-fg">{t('pos_debt_over')}</p>}
          <p className="mt-2 text-caption text-fg-muted">{t('pos_debt_note')}</p>
        </Card>
      </Section>

      <Section title={t('pos_store_commission')}>
        <Card padded={false}>
          <ul aria-label={t('pos_store_commission')} className="divide-y divide-line">
            {Object.entries(data.commission.rates).map(([channel, rate]) => (
              <li key={channel} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                <span className="font-bold">{t(CHANNEL_LABEL[channel] ?? 'pos_channel_pos')}</span>
                <span className="flex items-center gap-2">
                  <span className="font-bold">{percentText(rate.rate)}</span>
                  <Pill tone={rate.source === 'override' ? 'info' : 'neutral'}>{t(sourceLabel(rate.source))}</Pill>
                </span>
              </li>
            ))}
          </ul>
        </Card>
        <p className="px-1 text-caption text-fg-muted">{t('pos_store_commissionNote', { courier: t(data.commission.delivery_courier === 'fleet' ? 'pos_courier_fleet' : 'pos_courier_own') })}</p>
      </Section>

      <Section title={t('pos_store_invoices')}>
        {data.invoices.length === 0 ? (
          <p className="px-1 text-footnote text-fg-muted">{t('pos_store_noInvoices')}</p>
        ) : (
          <Card padded={false}>
            <ul aria-label={t('pos_store_invoices')} className="divide-y divide-line">
              {data.invoices.slice(0, 6).map((invoice) => (
                <li key={invoice.uuid} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                  <span className="flex min-w-0 flex-wrap items-center gap-2">
                    {onOpenInvoice ? (
                      <button type="button" className="font-bold text-brand hover:underline" onClick={() => onOpenInvoice(invoice.uuid)}>
                        {invoice.number}
                      </button>
                    ) : (
                      <span className="font-bold">{invoice.number}</span>
                    )}
                    <Pill tone={INVOICE_TONE[invoice.status] ?? 'neutral'}>{t(invoiceLabel(invoice.status))}</Pill>
                  </span>
                  <span className="text-footnote text-fg-muted">
                    {money(invoice.amount)} · {t('pos_inv_dueOn', { date: day(invoice.due_date) })}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        )}
      </Section>

      <Section title={t('pos_store_history')}>
        <Card padded={false}>
          <ul aria-label={t('pos_store_history')} className="divide-y divide-line">
            {data.history.map((row) => (
              <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-bold">{row.plan.name}</span>
                  <Pill tone={SUBSCRIPTION_TONE[row.status]}>{t(SUBSCRIPTION_LABEL[row.status])}</Pill>
                </span>
                <span className="text-footnote text-fg-muted">
                  {when(row.started_at)}
                  {row.cancelled_at ? ` → ${when(row.cancelled_at)}` : ''}
                </span>
              </li>
            ))}
            {data.history.length === 0 && <li className="p-3 text-footnote text-fg-muted">{t('pos_store_noHistory')}</li>}
          </ul>
        </Card>
      </Section>

      {dialog && (
        <BillingDialog
          kind={dialog}
          data={data}
          onClose={() => setDialog(null)}
          onDone={(message) => {
            setDialog(null);
            setNotice(message);
          }}
        />
      )}
    </div>
  );
}

const sourceLabel = (source: string) => (source === 'override' ? 'pos_source_override' : source === 'plan' ? 'pos_source_plan' : source === 'flat' ? 'pos_source_flat' : 'pos_source_default');

function FeatureList({ data }: { data: StoreBilling }) {
  const t = useT();
  const features = Object.entries(data.entitlements.features);
  // The plan a feature needs, by its name ("Pro tarifida")
  const names = new Map((useCatalog().data?.plans ?? []).map((plan) => [plan.code, plan.name]));
  return (
    <>
      {data.entitlements.legacy && <p className="mb-2 text-footnote text-fg-muted">{t('pos_store_legacy')}</p>}
      <ul aria-label={t('pos_store_features')} className="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
        {features.map(([key, on]) => (
          <li key={key} className="flex items-center gap-2 text-body">
            {on ? <Check aria-hidden="true" className="size-4 text-success-soft-fg" /> : <X aria-hidden="true" className="size-4 text-fg-muted" />}
            <span className={on ? 'text-fg' : 'text-fg-muted'}>
              {featureLabel(key, t)}
              <span className="sr-only"> — {t(on ? 'pos_feature_on' : 'pos_feature_off')}</span>
            </span>
            {!on && data.entitlements.required_plans[key] && <span className="text-caption text-fg-muted">({t('pos_feature_needs', { plan: names.get(data.entitlements.required_plans[key] ?? '') ?? data.entitlements.required_plans[key] ?? '' })})</span>}
          </li>
        ))}
      </ul>
    </>
  );
}
