'use client';

import Link from 'next/link';
import { useState, type ReactNode } from 'react';
import { ArrowLeft, Ban, CircleCheck, ExternalLink, LogOut, RefreshCw, ShieldAlert, UserRound } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, IconButton, InlineError, Notice, PageHeader, Pill, Section, Tabs } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { useUser, useUserAction, type UserAction, type UserCard } from '@/lib/api/customers';
import type { MessageKey } from '@/i18n';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { ReasonForm } from '../stores/StoreDialogs';
import { CARD_TABS, complaintHref, signed, SUPPORT_TONE, uzs, type UserCardTab } from './customersModel';

const TAB_LABEL: Record<UserCardTab, MessageKey> = {
  overview: 'customers_card_overview',
  orders: 'customers_card_orders',
  keshbek: 'customers_card_keshbek',
  complaints: 'customers_card_complaints',
  sessions: 'customers_card_sessions',
  audit: 'customers_card_audit',
};

// The user card (/users/[id], P4-31): profile, block state, order totals, orders and refunds, keshbek, complaints, sessions and
// devices, the store of a staff account, other accounts with the same phone or Telegram, audit entries. A part the member has
// no permission for is not shown (the backend sends null). Block, unblock and closing sessions ask for a reason; the shell
// asks for the step-up.
export function UserCardView({ id }: { id: number }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('users.view');
  const card = useUser(id, allowed);
  const [tab, setTab] = useState<UserCardTab>('overview');
  const [dialog, setDialog] = useState<UserAction['kind'] | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('customers_title')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const back = (
    <Link href="/customers" className="mb-3 inline-flex min-h-11 items-center gap-1.5 text-footnote font-bold text-brand hover:underline">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('customers_back')}
    </Link>
  );

  if (card.isError) {
    const missing = card.error instanceof ApiError && card.error.status === 404;
    return (
      <>
        {back}
        {missing ? (
          <EmptyState icon={UserRound} title={t('customers_userNotFound')} text={t('customers_userNotFoundText')} />
        ) : (
          <InlineError title={t('customers_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />
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

  const user = card.data;
  const { profile, block, actions } = user;
  const tabs = CARD_TABS.filter((key) => (key === 'orders' ? user.orders !== null : key === 'complaints' ? user.complaints !== null : key === 'audit' ? user.audit !== null : true));
  const current = tabs.includes(tab) ? tab : 'overview';
  const anyAction = actions.block || actions.unblock || actions.close_sessions;

  return (
    <>
      {back}
      <PageHeader
        title={profile.name}
        subtitle={[profile.role_label, user.membership?.store?.name, t('customers_since', { date: when(profile.created_at) })].filter(Boolean).join(' · ')}
        action={<IconButton icon={RefreshCw} label={t('customers_refresh')} onClick={() => void card.refetch()} disabled={card.isFetching} className={cx(card.isFetching && 'animate-spin motion-reduce:animate-none')} />}
      />
      <Notice message={notice} />

      <div className="flex max-w-5xl flex-col gap-4">
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone="neutral">{profile.role_label}</Pill>
            {block.blocked ? <Pill tone="danger">{t('customers_blocked')}</Pill> : <Pill tone="success">{t('customers_active')}</Pill>}
            <Pill tone="info">{user.stats.segment.label}</Pill>
          </div>
          {block.blocked && (
            <p className="break-words text-footnote text-danger-soft-fg">
              {t('customers_blockedBy', { time: when(block.at), name: block.by?.name ?? '—', reason: block.reason ?? '—' })}
            </p>
          )}
          {anyAction && (
            <div role="group" aria-label={t('customers_actions')} className="flex flex-wrap gap-2">
              {actions.block && (
                <Button variant="danger" icon={Ban} onClick={() => setDialog('block')}>
                  {t('customers_block')}
                </Button>
              )}
              {actions.unblock && (
                <Button icon={CircleCheck} onClick={() => setDialog('unblock')}>
                  {t('customers_unblock')}
                </Button>
              )}
              {actions.close_sessions && (
                <Button variant="secondary" icon={LogOut} onClick={() => setDialog('close_sessions')}>
                  {t('customers_closeSessions')}
                </Button>
              )}
            </div>
          )}
          {user.audit !== null && (
            <Link href={user.links.audit} className="inline-flex min-h-11 items-center gap-1 text-footnote font-bold text-brand hover:underline">
              {t('customers_openAudit')}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </Link>
          )}
        </Card>

        <Tabs idPrefix="user" label={t('customers_cardTabs')} value={current} onChange={setTab} tabs={tabs.map((key) => ({ id: key, label: t(TAB_LABEL[key]) }))} />
        <div role="tabpanel" id={`user-panel-${current}`} aria-labelledby={`user-tab-${current}`} className="flex flex-col gap-5">
          {current === 'overview' && <Overview user={user} />}
          {current === 'orders' && <Orders user={user} />}
          {current === 'keshbek' && <Keshbek user={user} />}
          {current === 'complaints' && <Complaints user={user} />}
          {current === 'sessions' && <Sessions user={user} />}
          {current === 'audit' && <Audit user={user} />}
        </div>
      </div>

      {dialog && (
        <ActionDialog
          kind={dialog}
          user={user}
          onClose={() => setDialog(null)}
          onDone={(message) => {
            setDialog(null);
            setNotice(message);
          }}
        />
      )}
    </>
  );
}

const DIALOG: Record<UserAction['kind'], { title: MessageKey; text: MessageKey; submit: MessageKey; done: MessageKey; tone: 'primary' | 'danger' }> = {
  block: { title: 'customers_blockTitle', text: 'customers_blockText', submit: 'customers_blockConfirm', done: 'customers_blockDone', tone: 'danger' },
  unblock: { title: 'customers_unblockTitle', text: 'customers_unblockText', submit: 'customers_unblock', done: 'customers_unblockDone', tone: 'primary' },
  close_sessions: { title: 'customers_closeTitle', text: 'customers_closeText', submit: 'customers_closeConfirm', done: 'customers_closeDone', tone: 'danger' },
};

function ActionDialog({ kind, user, onClose, onDone }: { kind: UserAction['kind']; user: UserCard; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const action = useUserAction(user.profile.id);
  const words = DIALOG[kind];
  return (
    <ReasonForm
      title={t(words.title)}
      text={t(words.text, { name: user.profile.name, sessions: user.sessions.active_tokens + user.sessions.refresh_sessions })}
      submitLabel={t(words.submit)}
      tone={words.tone}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ kind, reason }, { onSuccess: () => onDone(t(words.done)) })}
    />
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
  return <p className="p-4 text-footnote text-fg-muted">{text}</p>;
}

function Overview({ user }: { user: UserCard }) {
  const t = useT();
  const { profile, stats, membership } = user;
  return (
    <>
      <Section title={t('customers_profile')}>
        <Card>
          <Rows
            rows={[
              [t('customers_phone'), [profile.phone ?? t('customers_noPhone'), profile.phone_verified ? t('customers_phoneVerified') : null].filter(Boolean).join(' · ')],
              [t('customers_telegram'), profile.telegram.linked ? (profile.telegram.username ? `@${profile.telegram.username}` : t('customers_telegramYes')) : t('customers_telegramNo')],
              ...(profile.courier_type ? [[t('customers_courierType'), profile.courier_type.label] as [string, ReactNode]] : []),
              [t('customers_referredBy'), profile.referred_by ? <Link href={`/users/${profile.referred_by.id}`} className="font-bold text-brand hover:underline">{profile.referred_by.name}</Link> : '—'],
              [t('customers_referrals'), String(profile.referrals)],
              [t('customers_createdAt'), when(profile.created_at)],
            ]}
          />
        </Card>
      </Section>
      <Section title={t('customers_stats')}>
        <Card>
          <Rows
            rows={[
              [t('customers_statOrders'), t('customers_statOrdersValue', { total: stats.orders, delivered: stats.delivered, cancelled: stats.cancelled })],
              [t('customers_statSum'), uzs(stats.delivered_sum)],
              [t('customers_statAvg'), stats.avg_check ? uzs(stats.avg_check) : '—'],
              [t('customers_statLast'), when(stats.last_order_at)],
              [t('customers_statSegment'), stats.segment.label],
            ]}
          />
        </Card>
      </Section>
      {membership && (
        <Section title={t('customers_membership')}>
          <Card>
            <Rows
              rows={[
                [t('customers_store'), membership.store ? <Link href={`/stores/${membership.store.id}`} className="font-bold text-brand hover:underline">{membership.store.name}</Link> : '—'],
                [t('customers_branch'), membership.branch?.name ?? t('customers_allBranches')],
                [t('customers_role'), membership.role_label],
                [t('customers_loginPhone'), membership.login_phone ? t(membership.login_phone_proof === 'vouched' ? 'customers_loginVouched' : 'customers_loginSms') : t('customers_loginNone')],
                [t('customers_pin'), t(membership.pin_set ? 'customers_pinSet' : 'customers_pinNone')],
              ]}
            />
          </Card>
        </Section>
      )}
      <Section title={t('customers_linked')}>
        <Card padded={false}>
          {user.linked_accounts.length === 0 ? (
            <Empty text={t('customers_linkedEmpty')} />
          ) : (
            <ul aria-label={t('customers_linked')} className="divide-y divide-line">
              {user.linked_accounts.map((other) => (
                <li key={other.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <Link href={other.href} className="inline-flex min-h-11 items-center font-bold text-brand hover:underline">
                    {other.name}
                  </Link>
                  <span className="text-fg-muted">{[other.role_label, other.store?.name].filter(Boolean).join(' · ')}</span>
                  <span className="text-fg">{other.same.map((same) => t(same === 'phone' ? 'customers_samePhone' : 'customers_sameTelegram')).join(', ')}</span>
                  {other.blocked && <Pill tone="danger">{t('customers_blocked')}</Pill>}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Orders({ user }: { user: UserCard }) {
  const t = useT();
  return (
    <>
      <Section title={t('customers_recentOrders')}>
        <Card padded={false}>
          {(user.orders ?? []).length === 0 ? (
            <Empty text={t('customers_ordersEmpty')} />
          ) : (
            <ul aria-label={t('customers_recentOrders')} className="divide-y divide-line">
              {(user.orders ?? []).map((order) => (
                <li key={order.uuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <Link href={order.href} className="inline-flex min-h-11 items-center font-mono font-bold text-brand hover:underline">
                    #{order.short_code ?? order.uuid.slice(0, 8)}
                  </Link>
                  <span className="text-fg">{order.status_label}</span>
                  <span className="font-bold text-fg">{uzs(order.total)}</span>
                  <span className="break-words text-fg-muted">{order.store?.name ?? '—'}</span>
                  <span className="text-fg-muted">{when(order.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('customers_refunds')}>
        <Card padded={false}>
          {(user.refunds ?? []).length === 0 ? (
            <Empty text={t('customers_refundsEmpty')} />
          ) : (
            <ul aria-label={t('customers_refunds')} className="divide-y divide-line">
              {(user.refunds ?? []).map((refund) => (
                <li key={refund.uuid} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="font-bold text-fg">{uzs(refund.amount)}</span>
                    <Pill tone={refund.status === 'approved' ? 'success' : refund.status === 'rejected' ? 'neutral' : 'warning'}>{refund.status_label}</Pill>
                    {refund.order && (
                      <Link href={`/orders/${refund.order.uuid}`} className="font-mono font-bold text-brand hover:underline">
                        #{refund.order.short_code ?? refund.order.uuid.slice(0, 8)}
                      </Link>
                    )}
                  </span>
                  {refund.reason && <span className="break-words text-fg">{refund.reason}</span>}
                  <span className="text-fg-muted">{when(refund.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Keshbek({ user }: { user: UserCard }) {
  const t = useT();
  return (
    <Section title={t('customers_card_keshbek')}>
      <Card padded={false}>
        <p className="px-4 pt-4 text-body font-bold text-fg">{t('customers_balance', { amount: uzs(user.keshbek.balance) })}</p>
        <p className="px-4 pb-2 text-caption text-fg-muted">{t('customers_keshbekNote')}</p>
        {user.keshbek.entries.length === 0 ? (
          <Empty text={t('customers_keshbekEmpty')} />
        ) : (
          <ul aria-label={t('customers_keshbekMoves')} className="divide-y divide-line">
            {user.keshbek.entries.map((entry, index) => (
              <li key={`${entry.kind}-${entry.at}-${index}`} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                <span className="min-w-0 flex-1 break-words text-fg">{entry.label}</span>
                <span className={cx('font-bold', entry.amount.startsWith('-') ? 'text-danger-soft-fg' : 'text-success-soft-fg')}>{signed(entry.amount)}</span>
                <span className="w-full text-fg-muted sm:w-auto">{when(entry.at)}</span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function Complaints({ user }: { user: UserCard }) {
  const t = useT();
  return (
    <Section title={t('customers_card_complaints')}>
      <Card padded={false}>
        {(user.complaints ?? []).length === 0 ? (
          <Empty text={t('customers_userComplaintsEmpty')} />
        ) : (
          <ul aria-label={t('customers_card_complaints')} className="divide-y divide-line">
            {(user.complaints ?? []).map((complaint) => (
              <li key={complaint.uuid} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                <span className="flex flex-wrap items-center gap-2">
                  <Link href={complaintHref(complaint.uuid)} className="inline-flex min-h-11 items-center font-bold text-brand hover:underline">
                    {complaint.type_label}
                  </Link>
                  <Pill tone={SUPPORT_TONE[complaint.support_status]}>{complaint.support_status_label}</Pill>
                  {complaint.order?.short_code && <span className="font-mono text-fg-muted">#{complaint.order.short_code}</span>}
                </span>
                {complaint.description && <span className="break-words text-fg">{complaint.description}</span>}
                <span className="text-fg-muted">{when(complaint.created_at)}</span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function Sessions({ user }: { user: UserCard }) {
  const t = useT();
  const { sessions } = user;
  return (
    <>
      <Section title={t('customers_card_sessions')}>
        <Card padded={false}>
          <p className="px-4 pt-4 text-footnote text-fg">
            {t('customers_sessionsSummary', { tokens: sessions.active_tokens, refresh: sessions.refresh_sessions, time: when(sessions.last_used_at, t('customers_neverUsed')) })}
          </p>
          {sessions.tokens.length === 0 ? (
            <Empty text={t('customers_sessionsEmpty')} />
          ) : (
            <ul aria-label={t('customers_tokens')} className="divide-y divide-line">
              {sessions.tokens.map((token) => (
                <li key={token.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{token.kind_label}</span>
                  {token.device && <span className="text-fg">{token.device}</span>}
                  <span className="text-fg-muted">{t('customers_tokenUsed', { time: when(token.last_used_at, t('customers_neverUsed')) })}</span>
                  <span className="text-fg-muted">{t('customers_tokenCreated', { time: when(token.created_at) })}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
      <Section title={t('customers_devices')}>
        <Card padded={false}>
          {sessions.devices.length === 0 ? (
            <Empty text={t('customers_devicesEmpty')} />
          ) : (
            <ul aria-label={t('customers_devices')} className="divide-y divide-line">
              {sessions.devices.map((device) => (
                <li key={device.uuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-footnote">
                  <span className="font-bold text-fg">{device.name}</span>
                  <span className="text-fg">{[device.type_label, device.platform, device.app_version].filter(Boolean).join(' · ')}</span>
                  <span className="text-fg-muted">{t('customers_deviceSeen', { time: when(device.last_seen_at, t('customers_neverUsed')) })}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </Section>
    </>
  );
}

function Audit({ user }: { user: UserCard }) {
  const t = useT();
  return (
    <Section title={t('customers_card_audit')}>
      <Card padded={false}>
        {(user.audit ?? []).length === 0 ? (
          <Empty text={t('customers_auditEmpty')} />
        ) : (
          <ul aria-label={t('customers_card_audit')} className="divide-y divide-line">
            {(user.audit ?? []).map((entry) => (
              <li key={entry.id} className="flex flex-col gap-1 px-4 py-3 text-footnote">
                <span className="font-mono text-fg">{entry.action}</span>
                <span className="text-fg-muted">{[when(entry.created_at), entry.actor?.name].filter(Boolean).join(' · ')}</span>
                {entry.reason && <span className="break-words text-fg">{t('customers_auditReason', { reason: entry.reason })}</span>}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}
