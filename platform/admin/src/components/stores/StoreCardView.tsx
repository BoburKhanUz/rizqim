'use client';

import Link from 'next/link';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, Ban, CircleCheck, ExternalLink, Gauge, Link2, MessageSquare, RefreshCw, ShieldAlert, Sparkles, Store } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, formatMoney, formatOrderCode, IconButton, InlineError, Notice, PageHeader, Pill, Section, StatusBadge, Tabs } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { useStore, type StoreCard } from '@/lib/api/stores';
import type { MessageKey } from '@/i18n';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CrmPanel } from './CrmPanel';
import { StoreActionDialog, type CardDialog } from './StoreDialogs';
import { day, HEALTH_TONE, percent, statusTone } from './storesModel';
import { CARD_TABS, type CardTab } from './cardTabs';

const money = (value: string | number | null | undefined) => formatMoney(value ?? 0, 'uz');

const CHANNEL_LABEL: Record<string, MessageKey> = {
  rizqim_courier: 'stores_channel_rizqim_courier',
  own_delivery: 'stores_channel_own_delivery',
  pickup: 'stores_channel_pickup',
  pos: 'stores_channel_pos',
  aggregator: 'stores_channel_aggregator',
};
const INVOICE_LABEL: Record<string, MessageKey> = { issued: 'stores_invoice_issued', paid: 'stores_invoice_paid', void: 'stores_invoice_void' };

// The 360° card of a store (/stores/[id], P4-22): profile, health with its reasons, contacts, plan and subscription,
// branches, devices and fiscal state, money, orders and quality, complaints, status history and audit, CRM. The action
// buttons are the ones the backend offers this member (`actions`); each asks for a reason, the shell asks for the step-up.
// No "sign in as the owner" (owner decision): support reads everything here, the owner changes their own settings.
export function StoreCardView({ id, initialTab = 'overview' }: { id: number; initialTab?: CardTab }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const card = useStore(id, allowed);
  const [tab, setTab] = useState<CardTab>(initialTab);
  const [dialog, setDialog] = useState<CardDialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const back = (
    <Link href="/stores" className="mb-3 inline-flex min-h-11 items-center gap-1.5 text-footnote font-bold text-brand hover:underline">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('stores_back')}
    </Link>
  );

  if (card.isError) {
    const missing = card.error instanceof ApiError && card.error.status === 404;
    return (
      <>
        {back}
        {missing ? (
          <EmptyState icon={Store} title={t('stores_notFound')} text={t('stores_notFoundText')} />
        ) : (
          <InlineError title={t('stores_cardLoadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />
        )}
      </>
    );
  }
  if (!card.isSuccess) {
    return (
      <>
        {back}
        <CardSkeletons count={3} lines={4} label={t('loading')} />
      </>
    );
  }

  const store = card.data;
  const { actions, profile } = store;
  const done = (message: string) => {
    setDialog(null);
    setNotice(message);
  };
  const anyAction = actions.suspend || actions.activate || actions.credit_limit || actions.display || actions.telegram_link || actions.message_owner;

  return (
    <>
      {back}
      <PageHeader
        title={profile.name}
        subtitle={[profile.status_label, profile.tin ? `${t('stores_tin')} ${profile.tin}` : null].filter(Boolean).join(' · ')}
        action={<IconButton icon={RefreshCw} label={t('stores_refresh')} onClick={() => void card.refetch()} disabled={card.isFetching} className={cx(card.isFetching && 'animate-spin motion-reduce:animate-none')} />}
      />
      <Notice message={notice} />

      <div className="flex max-w-5xl flex-col gap-4">
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone={statusTone(profile.status)}>{profile.status_label}</Pill>
            <Pill tone={HEALTH_TONE[store.health.level]}>
              {store.health.label} · {t('stores_score', { score: store.health.score })}
            </Pill>
            {!profile.is_active && <Pill tone="warning">{t('stores_hidden')}</Pill>}
            {profile.is_premium && <Pill tone="info">{t('stores_premium')}</Pill>}
            {profile.is_featured && <Pill tone="info">{t('stores_featured')}</Pill>}
            {store.application && store.application.stage !== 'active' && (
              <Link href={`/stores/applications/${store.application.uuid}`} className="text-footnote font-bold text-brand hover:underline">
                {t('stores_application', { stage: store.application.stage_label })}
              </Link>
            )}
          </div>
          {profile.is_order_locked && <p className="text-footnote text-danger-soft-fg">{t('stores_orderLocked')}</p>}
          {anyAction && (
            <div role="group" aria-label={t('stores_actions')} className="flex flex-wrap gap-2">
              {actions.suspend && (
                <Button variant="danger" icon={Ban} onClick={() => setDialog('suspend')}>
                  {t('stores_suspend')}
                </Button>
              )}
              {actions.activate && (
                <Button icon={CircleCheck} onClick={() => setDialog('activate')}>
                  {t('stores_activate')}
                </Button>
              )}
              {actions.credit_limit && (
                <Button variant="secondary" icon={Gauge} onClick={() => setDialog('credit_limit')}>
                  {t('stores_creditLimit')}
                </Button>
              )}
              {actions.display && (
                <Button variant="secondary" icon={Sparkles} onClick={() => setDialog('display')}>
                  {t('stores_display')}
                </Button>
              )}
              {actions.telegram_link && (
                <Button variant="secondary" icon={Link2} onClick={() => setDialog('telegram_link')}>
                  {t('stores_telegramLink')}
                </Button>
              )}
              {actions.message_owner && (
                <Button variant="secondary" icon={MessageSquare} onClick={() => setDialog('message_owner')}>
                  {t('stores_messageOwner')}
                </Button>
              )}
            </div>
          )}
          <div className="flex flex-wrap gap-x-4 gap-y-1 text-footnote">
            <Link href={store.links.orders} className="inline-flex min-h-11 items-center gap-1 font-bold text-brand hover:underline">
              {t('stores_openOrders')}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </Link>
            {can('audit.view') && (
              <Link href={store.links.audit} className="inline-flex min-h-11 items-center gap-1 font-bold text-brand hover:underline">
                {t('stores_openAudit')}
                <ExternalLink aria-hidden="true" className="size-3.5" />
              </Link>
            )}
            {actions.commission_override && (
              <Link href={store.links.subscription} className="inline-flex min-h-11 items-center gap-1 font-bold text-brand hover:underline" title={t('stores_commissionHint')}>
                {t('stores_openSubscription')}
                <ExternalLink aria-hidden="true" className="size-3.5" />
              </Link>
            )}
          </div>
        </Card>

        <Tabs
          idPrefix="store"
          label={t('stores_cardTabs')}
          value={tab}
          onChange={setTab}
          tabs={CARD_TABS.map((key) => ({ id: key, label: t(`stores_card_${key}`) }))}
        />
        <div role="tabpanel" id={`store-panel-${tab}`} aria-labelledby={`store-tab-${tab}`} className="flex flex-col gap-5">
          {tab === 'overview' && <Overview store={store} />}
          {tab === 'branches' && <Branches store={store} />}
          {tab === 'devices' && <Devices store={store} />}
          {tab === 'finance' && <Finance store={store} />}
          {tab === 'orders' && <Orders store={store} />}
          {tab === 'complaints' && <Complaints store={store} />}
          {tab === 'history' && <History store={store} />}
          {tab === 'crm' && <CrmPanel crm={store.crm} storeId={profile.id} canManage={actions.manage} onNotice={setNotice} />}
        </div>
      </div>

      {dialog && <StoreActionDialog kind={dialog} store={store} onDone={done} onClose={() => setDialog(null)} />}
    </>
  );
}

function Rows({ rows }: { rows: Array<[string, ReactNode]> }) {
  return (
    <dl className="grid grid-cols-1 gap-x-4 gap-y-2 text-footnote sm:grid-cols-[auto_1fr]">
      {rows.map(([label, value]) => (
        <div key={label} className="contents">
          <dt className="text-fg-muted">{label}</dt>
          <dd className="break-words text-fg">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

function Empty({ text }: { text: string }) {
  return <p className="text-footnote text-fg-muted">{text}</p>;
}

function Overview({ store }: { store: StoreCard }) {
  const t = useT();
  const { profile, health, plan } = store;
  const subscription = plan.subscription;
  return (
    <>
      <Section title={t('stores_health')}>
        <Card className="flex flex-col gap-2">
          <p className="text-footnote font-bold text-fg">
            {health.label} · {t('stores_score', { score: health.score })}
          </p>
          {health.reasons.length === 0 ? (
            <Empty text={t('stores_healthNoReasons')} />
          ) : (
            <ul aria-label={t('stores_reasonsLabel')} className="flex flex-col gap-1.5">
              {health.reasons.map((reason) => (
                <li key={reason.key} className="flex flex-wrap items-center gap-2 text-footnote text-fg">
                  <Pill tone={reason.level === 'churn_risk' ? 'danger' : 'warning'}>{t(reason.level === 'churn_risk' ? 'stores_health_churn_risk' : 'stores_health_attention')}</Pill>
                  <span className="min-w-0 flex-1 break-words">{reason.label}</span>
                </li>
              ))}
            </ul>
          )}
          <p className="text-caption text-fg-muted">{t('stores_healthComputed', { time: when(health.computed_at) })}</p>
        </Card>
      </Section>

      <Section title={t('stores_profile')}>
        <Card>
          <Rows
            rows={[
              [t('stores_phone'), profile.phone ?? '—'],
              [t('stores_tin'), profile.tin ?? '—'],
              [t('stores_legalName'), profile.legal_name ?? '—'],
              [t('stores_address'), profile.address ?? '—'],
              [t('stores_createdAt'), when(profile.created_at)],
              [t('stores_ratingLabel'), t('stores_rating', { rating: profile.rating, count: profile.review_count })],
              [t('stores_telegram'), [profile.telegram.store_chat_linked ? t('stores_storeChat') : t('stores_storeChatMissing'), profile.telegram.owner_linked ? t('stores_ownerLinked') : t('stores_ownerNotLinked')].join(' · ')],
            ]}
          />
        </Card>
      </Section>

      <Section title={t('stores_contacts')}>
        <Card padded={false}>
          {store.contacts.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_noContacts')} />
            </div>
          ) : (
            <ul aria-label={t('stores_contacts')} className="divide-y divide-line">
              {store.contacts.map((contact, index) => (
                <li key={`${contact.kind}-${contact.user_id ?? index}`} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{contact.name}</span>
                  <span className="text-fg-muted">{contact.role_label}</span>
                  <span className="text-fg">{contact.phone ?? '—'}</span>
                  {contact.kind === 'staff' && <Pill tone={contact.telegram_linked ? 'success' : 'neutral'}>{contact.telegram_linked ? t('stores_ownerLinked') : t('stores_ownerNotLinked')}</Pill>}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>

      <Section title={t('stores_planTitle')}>
        <Card className="flex flex-col gap-1.5 text-footnote text-fg">
          {subscription ? (
            <>
              <p className="font-bold">
                {subscription.plan.name} · {subscription.status_label} · {t(subscription.billing_period === 'yearly' ? 'stores_periodYearly' : 'stores_periodMonthly')}
              </p>
              {subscription.trial_ends_at && <p>{t('stores_planTrialEnds', { date: when(subscription.trial_ends_at) })}</p>}
              <p>{t('stores_planPeriod', { period: `${day(subscription.current_period.start)} – ${day(subscription.current_period.end)}` })}</p>
              {subscription.next_plan && <p>{t('stores_planNext', { name: subscription.next_plan.name })}</p>}
              {subscription.cancel_at_period_end && <p>{t('stores_planCancel')}</p>}
            </>
          ) : (
            <p>{t('stores_planNoSub')}</p>
          )}
          {plan.entitlements.settings_locked && <p className="font-bold text-danger-soft-fg">{t('stores_planLocked', { date: when(plan.entitlements.locked_since) })}</p>}
          {Object.entries(plan.entitlements.devices ?? {}).map(([type, device]) => (
            <p key={type}>{t('stores_planDevices', { type: type === 'kds' ? t('stores_deviceKds') : t('stores_devicePos'), active: device.active, included: device.included ?? '∞' })}</p>
          ))}
          <ul aria-label={t('stores_commissionRates')} className="mt-1 flex flex-col gap-0.5">
            {Object.entries(plan.commission.rates).map(([channel, rate]) => (
              <li key={channel}>{t('stores_commissionRate', { channel: t(CHANNEL_LABEL[channel] ?? 'stores_channel_other'), rate: Number(rate.rate) })}</li>
            ))}
          </ul>
          {plan.commission.flat_rate !== null && <p className="text-caption text-fg-muted">{t('stores_commissionFlat', { rate: Number(plan.commission.flat_rate) })}</p>}
        </Card>
      </Section>
    </>
  );
}

function Branches({ store }: { store: StoreCard }) {
  const t = useT();
  return (
    <Section title={t('stores_card_branches')}>
      <Card padded={false}>
        {store.branches.length === 0 ? (
          <div className="p-4">
            <Empty text={t('stores_branchesEmpty')} />
          </div>
        ) : (
          <ul aria-label={t('stores_card_branches')} className="divide-y divide-line">
            {store.branches.map((branch) => (
              <li key={branch.id} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-bold text-fg">{branch.name}</span>
                  {!branch.is_active && <Pill tone="neutral">{t('stores_branchOff')}</Pill>}
                </span>
                <span className="break-words text-fg">{[branch.region?.name, branch.district?.name, branch.address].filter(Boolean).join(', ') || '—'}</span>
                <span className="text-fg-muted">
                  {[branch.phone, branch.open_time && branch.close_time ? t('stores_branchHours', { open: branch.open_time.slice(0, 5), close: branch.close_time.slice(0, 5) }) : null].filter(Boolean).join(' · ')}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function Devices({ store }: { store: StoreCard }) {
  const t = useT();
  const { devices, fiscal } = store;
  return (
    <>
      <Section title={t('stores_devices')}>
        <Card padded={false}>
          <p className="px-4 pt-3 text-footnote text-fg">
            {t('stores_devicesSummary', { online: devices.terminals_online, total: devices.terminals, pos: devices.by_type.pos ?? 0, kds: devices.by_type.kds ?? 0 })}
          </p>
          {devices.items.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_devicesEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_devices')} className="divide-y divide-line">
              {devices.items.map((device) => (
                <li key={device.uuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{device.name}</span>
                  <span className="text-fg-muted">{device.type_label}</span>
                  {device.terminal && <Pill tone={device.online ? 'success' : 'warning'}>{device.online ? t('stores_online') : t('stores_offline')}</Pill>}
                  <span className="text-fg-muted">{t('stores_lastSeen', { time: when(device.last_seen_at) })}</span>
                  {device.app_version && <span className="text-fg-muted">{t('stores_appVersion', { version: device.app_version })}</span>}
                  {device.branch && <span className="text-fg-muted">{device.branch.name}</span>}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('stores_fiscal')}>
        <Card className="flex flex-col gap-1.5 text-footnote text-fg">
          <p className="font-bold">{fiscal.enabled ? t('stores_fiscalOn', { driver: fiscal.driver ?? '—' }) : t('stores_fiscalOff')}</p>
          <p>{t('stores_fiscalFailed', { count: fiscal.failed_7d })}</p>
          <p>{t('stores_fiscalPending', { count: fiscal.pending })}</p>
          {fiscal.enabled && <p>{t('stores_fiscalMxik', { count: fiscal.missing_mxik })}</p>}
          {fiscal.last_failed.length > 0 && (
            <ul className="mt-1 flex flex-col gap-1">
              {fiscal.last_failed.map((receipt) => (
                <li key={receipt.uuid} className="break-words text-danger-soft-fg">
                  {when(receipt.created_at)} · {money(receipt.total)} · {receipt.error ?? '—'}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Finance({ store }: { store: StoreCard }) {
  const t = useT();
  const { finance } = store;
  return (
    <>
      <Section title={t('stores_balance')}>
        <Card>
          <Rows
            rows={[
              [t('stores_debtBalance'), <span key="debt" className="font-bold">{money(finance.debt_balance)}</span>],
              [t('stores_subscriptionDebt'), money(finance.subscription_debt)],
              [t('stores_commissionDebt'), <span key="commission" className={finance.over_limit ? 'font-bold text-danger-soft-fg' : undefined}>{money(finance.commission_debt)}</span>],
              [t('stores_limit'), finance.over_limit ? `${money(finance.credit_limit)} · ${t('stores_overLimitPill')}` : money(finance.credit_limit)],
              [t('stores_wallet'), money(finance.wallet_balance)],
              [t('stores_available'), money(finance.available_for_payout)],
            ]}
          />
        </Card>
      </Section>
      <Section title={t('stores_invoices')}>
        <Card padded={false}>
          {store.plan.invoices.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_invoicesEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_invoices')} className="divide-y divide-line">
              {store.plan.invoices.slice(0, 6).map((invoice) => (
                <li key={invoice.uuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-mono font-bold text-fg">{invoice.number}</span>
                  <span className="text-fg">{money(invoice.amount)}</span>
                  <Pill tone={invoice.status === 'paid' ? 'success' : invoice.status === 'void' ? 'neutral' : 'warning'}>{t(INVOICE_LABEL[invoice.status] ?? 'stores_invoice_issued')}</Pill>
                  {invoice.status === 'issued' && Number(invoice.outstanding) > 0 && <span className="text-fg">{t('stores_invoiceOutstanding', { amount: money(invoice.outstanding) })}</span>}
                  <span className="text-fg-muted">{t('stores_invoiceDue', { date: day(invoice.due_date) })}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('stores_payouts')}>
        <Card padded={false}>
          {finance.payouts.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_payoutsEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_payouts')} className="divide-y divide-line">
              {finance.payouts.map((payout) => (
                <li key={payout.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{money(payout.amount)}</span>
                  <span className="font-mono text-fg-muted">{payout.card ?? '—'}</span>
                  <span className="text-fg-muted">{payout.status}</span>
                  <span className="text-fg-muted">{when(payout.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('stores_ledger')}>
        <Card padded={false}>
          {finance.ledger.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_ledgerEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_ledger')} className="divide-y divide-line">
              {finance.ledger.map((entry) => (
                <li key={entry.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="text-fg-muted">{entry.type}</span>
                  <span className="font-bold text-fg">{money(entry.amount)}</span>
                  <span className="min-w-0 flex-1 break-words text-fg-muted">{entry.notes ?? ''}</span>
                  <span className="text-fg-muted">{when(entry.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Orders({ store }: { store: StoreCard }) {
  const t = useT();
  const { quality, last_30_days: channels, recent } = store.orders;
  return (
    <>
      <Section title={t('stores_quality')}>
        <Card className="flex flex-col gap-1.5 text-footnote text-fg">
          {!quality || quality.orders === 0 ? (
            <Empty text={t('stores_qualityEmpty')} />
          ) : (
            <>
              <p>{t('stores_qualityOrders', { count: quality.orders })}</p>
              <p>{t('stores_qualityAcceptance', { value: percent(quality.acceptance_rate) })}</p>
              <p>{t('stores_qualityAccept', { value: quality.avg_accept_minutes === null ? '—' : t('stores_minutes', { value: quality.avg_accept_minutes }) })}</p>
              <p>{t('stores_qualityPrep', { value: quality.avg_prep_minutes === null ? '—' : t('stores_minutes', { value: quality.avg_prep_minutes }) })}</p>
              <p>{t('stores_qualityCancel', { value: percent(quality.cancel_rate) })}</p>
            </>
          )}
        </Card>
      </Section>
      <Section title={t('stores_byChannel')}>
        <Card className="flex flex-col gap-1 text-footnote text-fg">
          {channels.length === 0 ? <Empty text={t('stores_recentEmpty')} /> : channels.map((row) => <p key={row.channel}>{t('stores_channelRow', { channel: row.channel, count: row.orders, total: money(row.total) })}</p>)}
        </Card>
      </Section>
      <Section title={t('stores_recentOrders')}>
        <Card padded={false}>
          {recent.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_recentEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_recentOrders')} className="divide-y divide-line">
              {recent.map((order) => (
                <li key={order.uuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  {order.marketplace ? (
                    <Link href={`/orders/${order.uuid}`} className="font-mono font-bold text-brand hover:underline">
                      {formatOrderCode(order)}
                    </Link>
                  ) : (
                    <span className="font-mono font-bold text-fg">{formatOrderCode(order)}</span>
                  )}
                  <StatusBadge status={order.status} label={order.status_label} />
                  <span className="text-fg">{money(order.total)}</span>
                  <span className="text-fg-muted">{order.channel}</span>
                  <span className="text-fg-muted">{when(order.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Complaints({ store }: { store: StoreCard }) {
  const t = useT();
  const { complaints } = store;
  return (
    <Section title={t('stores_card_complaints')}>
      <Card padded={false}>
        <p className="px-4 pt-3 text-footnote text-fg">{t('stores_complaintsSummary', { open: complaints.open, month: complaints.last_30_days })}</p>
        {complaints.items.length === 0 ? (
          <div className="p-4">
            <Empty text={t('stores_complaintsEmpty')} />
          </div>
        ) : (
          <ul aria-label={t('stores_card_complaints')} className="divide-y divide-line">
            {complaints.items.map((complaint) => (
              <li key={complaint.uuid} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-bold text-fg">{complaint.type}</span>
                  <Pill tone={complaint.status === 'closed' ? 'neutral' : 'warning'}>{complaint.status}</Pill>
                  {complaint.order && (
                    <Link href={`/orders/${complaint.order.uuid}`} className="font-mono text-brand hover:underline">
                      {formatOrderCode(complaint.order)}
                    </Link>
                  )}
                  <span className="text-fg-muted">{when(complaint.created_at)}</span>
                </span>
                <span className="break-words text-fg">{complaint.description}</span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function History({ store }: { store: StoreCard }) {
  const t = useT();
  return (
    <>
      <Section title={t('stores_statusHistory')}>
        <Card padded={false}>
          {store.status_history.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_statusHistoryEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_statusHistory')} className="divide-y divide-line">
              {store.status_history.map((row, index) => (
                <li key={index} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{t('stores_statusChange', { from: row.from_label ?? '—', to: row.to_label })}</span>
                  <span className="break-words text-fg">{row.comment ?? ''}</span>
                  <span className="text-fg-muted">
                    {row.by?.name ?? t('stores_auditSystem')} · {when(row.created_at)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('stores_audit')}>
        <Card padded={false}>
          {store.audit.length === 0 ? (
            <div className="p-4">
              <Empty text={t('stores_auditEmpty')} />
            </div>
          ) : (
            <ul aria-label={t('stores_audit')} className="divide-y divide-line">
              {store.audit.map((entry) => (
                <li key={entry.id} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                  <span className="font-mono text-fg">{entry.action}</span>
                  {entry.reason && <span className="break-words text-fg">{entry.reason}</span>}
                  <span className="text-fg-muted">
                    {entry.actor?.name ?? t('stores_auditSystem')} · {when(entry.created_at)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}
