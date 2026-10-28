'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Pencil, Plus, Search, Ticket, Trash2 } from 'lucide-react';
import { Button, Card, Dialog, Field, fieldClass, IconButton, Notice, Pill, Section, Switch } from '@rizqim/ui';
import {
  CODE_STATUSES,
  DEFAULT_CODE_FILTERS,
  useCode,
  useCodes,
  useDeleteCode,
  useSetCodeActive,
  type CodeFilters,
  type PromoCode,
} from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CodeDialog } from './CodeDialog';
import { CODE_LABEL, CODE_TONE, discountText, money } from './marketingModel';
import { Chip, ChipRow, ListState, Pager, ReasonDialog } from './MarketingParts';

// Promo-kodlar (P4-31): the codes with how they are used (uses, customers, the discount given, the orders' total). Reading needs
// marketing.view; adding, changing and switching on or off marketing.manage with a reason; deleting (only a code no order ever
// carried) also the step-up. A code that was used keeps its discount: a new campaign is a new code.

type Action = { kind: 'toggle'; code: PromoCode } | { kind: 'delete'; code: PromoCode };

export function CodesTab() {
  const t = useT();
  const { can } = useCan();
  const manage = can('marketing.manage');
  const [filters, setFilters] = useState<CodeFilters>(DEFAULT_CODE_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search.trim() ? previous : { ...previous, q: search.trim(), page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);
  const codes = useCodes(filters);
  const [editing, setEditing] = useState<PromoCode | 'new' | null>(null);
  const [opened, setOpened] = useState<number | null>(null);
  const [action, setAction] = useState<Action | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const page = codes.data;
  const done = (message: string) => {
    setEditing(null);
    setAction(null);
    setNotice(message);
  };

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <Notice message={notice} />
      <div className="flex flex-wrap items-end justify-between gap-2">
        <Field label={t('marketing_code_search')} className="w-full sm:w-72">
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" className={`${fieldClass} pl-9`} value={search} placeholder="YANGI10" onChange={(event) => setSearch(event.target.value)} />
            </div>
          )}
        </Field>
        {manage && (
          <Button icon={Plus} onClick={() => setEditing('new')}>
            {t('marketing_code_new')}
          </Button>
        )}
      </div>
      <ChipRow label={t('marketing_code_statusFilter')}>
        <Chip on={filters.status === ''} onClick={() => setFilters({ ...filters, status: '', page: 1 })}>
          {t('marketing_all')}
        </Chip>
        {CODE_STATUSES.map((value) => (
          <Chip key={value} on={filters.status === value} onClick={() => setFilters({ ...filters, status: value, page: 1 })}>
            {t(CODE_LABEL[value])}
          </Chip>
        ))}
      </ChipRow>

      <ListState
        loading={codes.isPending}
        error={codes.error}
        fetching={codes.isFetching}
        onRetry={() => void codes.refetch()}
        empty={(page?.data.length ?? 0) === 0}
        emptyTitle={filters.q || filters.status ? t('marketing_code_emptyFiltered') : t('marketing_code_empty')}
        emptyText={filters.q || filters.status ? undefined : t('marketing_code_emptyText')}
        icon={Ticket}
        errorTitle={t('marketing_loadError')}
      >
        <p className="px-1 text-caption text-fg-muted">{t('marketing_total', { count: page?.total ?? 0 })}</p>
        <ul aria-label={t('marketing_code_list')} className="flex flex-col gap-3">
          {page?.data.map((code) => (
            <li key={code.id}>
              <Card className="flex flex-col gap-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="flex min-w-0 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="break-all font-mono text-headline font-bold text-fg">{code.code}</h3>
                      <Pill tone={CODE_TONE[code.status]}>{t(CODE_LABEL[code.status])}</Pill>
                    </div>
                    <p className="text-body text-fg">{t('marketing_code_gives', { discount: discountText(code, t) })}</p>
                    <p className="text-footnote text-fg-muted">
                      {[
                        code.store ? t('marketing_code_onlyStore', { name: code.store.name }) : t('marketing_code_allStores'),
                        Number(code.min_order_amount) > 0 ? t('marketing_code_fromAmount', { amount: money(code.min_order_amount) }) : null,
                        code.expires_at ? t('marketing_code_until', { date: when(code.expires_at) }) : t('marketing_code_noExpiry'),
                      ]
                        .filter(Boolean)
                        .join(' · ')}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-1">
                    <Button variant="secondary" onClick={() => setOpened(code.id)} aria-label={t('marketing_code_open', { code: code.code })}>
                      {t('marketing_code_details')}
                    </Button>
                    {manage && (
                      <>
                        <Switch checked={code.is_active} onChange={() => setAction({ kind: 'toggle', code })} label={t('marketing_code_toggle', { code: code.code })} />
                        <IconButton icon={Pencil} label={t('marketing_code_edit', { code: code.code })} onClick={() => setEditing(code)} />
                        {!code.locked && <IconButton icon={Trash2} variant="danger" label={t('marketing_code_delete', { code: code.code })} onClick={() => setAction({ kind: 'delete', code })} />}
                      </>
                    )}
                  </div>
                </div>
                <Usage code={code} />
              </Card>
            </li>
          ))}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => setFilters({ ...filters, page: next })} />}
      </ListState>

      {editing && <CodeDialog code={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onDone={done} />}
      {opened !== null && <CodeCardDialog id={opened} onClose={() => setOpened(null)} />}
      {action?.kind === 'toggle' && <ToggleDialog code={action.code} onClose={() => setAction(null)} onDone={done} />}
      {action?.kind === 'delete' && <DeleteDialog code={action.code} onClose={() => setAction(null)} onDone={done} />}
    </div>
  );
}

// The usage numbers of a code: how it is used against its limits, who used it and what it cost
function Usage({ code }: { code: PromoCode }) {
  const t = useT();
  const { uses, unique_customers: customers, discount_total: discount, gmv } = code.stats;
  return (
    <dl aria-label={t('marketing_code_stats', { code: code.code })} className="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-4">
      <Stat label={t('marketing_code_uses')} value={code.usage_limit ? t('marketing_code_usesOf', { uses, limit: code.usage_limit }) : String(uses)} />
      <Stat label={t('marketing_code_customers')} value={String(customers)} />
      <Stat label={t('marketing_code_discountTotal')} value={money(discount)} />
      <Stat label={t('marketing_code_gmv')} value={money(gmv)} />
    </dl>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <dt className="text-caption font-bold text-fg-muted">{label}</dt>
      <dd className="text-body font-bold text-fg">{value}</dd>
    </div>
  );
}

// The card of a code: its rules in words and the last orders that used it (each opens in Buyurtmalar)
function CodeCardDialog({ id, onClose }: { id: number; onClose: () => void }) {
  const t = useT();
  const card = useCode(id);
  const code = card.data;
  return (
    <Dialog open title={code ? t('marketing_code_cardTitle', { code: code.code }) : t('marketing_code_cardLoading')} closeLabel={t('close')} onClose={onClose}>
      {card.error ? (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {errorText(card.error, t)}
        </p>
      ) : !code ? (
        <p className="text-footnote text-fg-muted">{t('loading')}</p>
      ) : (
        <div className="flex flex-col gap-4">
          <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            <Stat label={t('marketing_code_type')} value={code.type_label} />
            <Stat label={t('marketing_code_discount')} value={discountText(code, t)} />
            <Stat label={t('marketing_code_minOrder')} value={money(code.min_order_amount)} />
            <Stat label={t('marketing_code_perUser')} value={code.per_user_limit ? String(code.per_user_limit) : t('marketing_code_unlimited')} />
            <Stat label={t('marketing_code_usageLimit')} value={code.usage_limit ? String(code.usage_limit) : t('marketing_code_unlimited')} />
            <Stat label={t('marketing_code_store')} value={code.store?.name ?? t('marketing_code_allStores')} />
          </dl>
          <Usage code={code} />
          {code.locked && <p className="text-footnote text-fg-muted">{t('marketing_code_lockedShort')}</p>}
          <Section title={t('marketing_code_recent')}>
            {code.recent_orders.length === 0 ? (
              <p className="px-1 text-footnote text-fg-muted">{t('marketing_code_noOrders')}</p>
            ) : (
              <ul aria-label={t('marketing_code_recent')} className="divide-y divide-line rounded-card border border-line">
                {code.recent_orders.map((order) => (
                  <li key={order.uuid} className="flex flex-wrap items-center justify-between gap-2 p-3 text-footnote">
                    <Link href={`/orders/${order.uuid}`} className="font-bold text-brand underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                      {when(order.created_at)}
                    </Link>
                    <span className="text-fg-muted">{t('marketing_code_orderLine', { discount: money(order.discount), total: money(order.total) })}</span>
                  </li>
                ))}
              </ul>
            )}
          </Section>
        </div>
      )}
    </Dialog>
  );
}

function ToggleDialog({ code, onDone, onClose }: { code: PromoCode; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const toggle = useSetCodeActive();
  const turnOn = !code.is_active;
  return (
    <ReasonDialog
      title={turnOn ? t('marketing_code_onTitle') : t('marketing_code_offTitle')}
      text={turnOn ? t('marketing_code_onText', { code: code.code }) : t('marketing_code_offText', { code: code.code })}
      submitLabel={turnOn ? t('marketing_turnOn') : t('marketing_turnOff')}
      busy={toggle.isPending}
      error={toggle.error ? errorText(toggle.error, t) : null}
      reasonError={fieldError(toggle.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => toggle.mutate({ id: code.id, active: turnOn, reason }, { onSuccess: () => onDone(turnOn ? t('marketing_code_turnedOn') : t('marketing_code_turnedOff')) })}
    />
  );
}

function DeleteDialog({ code, onDone, onClose }: { code: PromoCode; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const remove = useDeleteCode();
  return (
    <ReasonDialog
      title={t('marketing_code_deleteTitle')}
      text={t('marketing_code_deleteText', { code: code.code })}
      submitLabel={t('marketing_delete')}
      tone="danger"
      busy={remove.isPending}
      error={remove.error ? errorText(remove.error, t) : null}
      reasonError={fieldError(remove.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => remove.mutate({ id: code.id, reason }, { onSuccess: () => onDone(t('marketing_code_deleted')) })}
    />
  );
}
