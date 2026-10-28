'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { ChevronRight, Megaphone, Search, ShieldAlert, Store } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, formatMoney, InlineError, PageHeader, Pill } from '@rizqim/ui';
import { DEFAULT_STORE_FILTERS, HEALTH_LEVELS, useStores, type StoreFilters, type StoreRow } from '@/lib/api/stores';
import { errorText } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { RegionSelect } from './StorePicker';
import { StoresNav } from './StoresNav';
import { day, HEALTH_LABEL, HEALTH_TONE, statusTone } from './storesModel';

// Ovqatxonalar (P4-22, docs/PHASE4_PLAN.md 4.3): every store with its health, plan and debt. The health chips count the
// stores under the other filters; a row opens the 360° card. Phones are not in the list (the card has them).

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');

export function StoresView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const [filters, setFilters] = useState<StoreFilters>(DEFAULT_STORE_FILTERS);
  const [search, setSearch] = useState('');

  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const stores = useStores(filters, allowed);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const set = <K extends keyof StoreFilters>(name: K, value: StoreFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));
  const page = stores.data;
  const counts = page?.health_counts;

  return (
    <>
      <PageHeader
        title={t('stores_title')}
        subtitle={t('stores_subtitle')}
        action={
          can('stores.manage') ? (
            <Link href="/stores/broadcasts" className="inline-flex min-h-11 items-center gap-1.5 rounded-control px-3 text-footnote font-bold text-brand hover:bg-surface-2">
              <Megaphone aria-hidden="true" className="size-4" />
              {t('stores_broadcastNew')}
            </Link>
          ) : undefined
        }
      />
      <StoresNav current="list" />

      <section aria-label={t('orders_filters')} className="mb-4 flex max-w-5xl flex-col gap-3">
        <div role="group" aria-label={t('stores_healthFilter')} className="flex flex-wrap gap-2">
          <button type="button" aria-pressed={filters.health === ''} className={cx(CHIP, chipTone(filters.health === ''))} onClick={() => set('health', '')}>
            {counts ? t('stores_healthAll', { count: counts.all }) : t('stores_anyDebt')}
          </button>
          {[...HEALTH_LEVELS, 'none' as const].map((level) => (
            <button key={level} type="button" aria-pressed={filters.health === level} className={cx(CHIP, chipTone(filters.health === level))} onClick={() => set('health', level)}>
              {counts ? t('stores_healthCount', { label: t(HEALTH_LABEL[level]), count: counts[level] }) : t(HEALTH_LABEL[level])}
            </button>
          ))}
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={t('stores_search')}>
            {(control) => (
              <div className="relative">
                <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                <input {...control} type="search" value={search} placeholder={t('stores_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
              </div>
            )}
          </Field>
          <Field label={t('stores_status')}>
            {(control) => (
              <select {...control} className={fieldClass} value={filters.status} onChange={(event) => set('status', event.target.value)}>
                <option value="">{t('stores_anyStatus')}</option>
                {Object.entries(page?.statuses ?? {}).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <RegionSelect value={filters.region_id} onChange={(value) => set('region_id', value)} label={t('stores_region')} />
          <Field label={t('stores_debt')}>
            {(control) => (
              <select {...control} className={fieldClass} value={filters.debt} onChange={(event) => set('debt', event.target.value as StoreFilters['debt'])}>
                <option value="">{t('stores_anyDebt')}</option>
                <option value="has_debt">{t('stores_hasDebt')}</option>
                <option value="over_limit">{t('stores_overLimit')}</option>
              </select>
            )}
          </Field>
          <Field label={t('stores_plan')}>
            {(control) => (
              <select {...control} className={fieldClass} value={filters.plan} onChange={(event) => set('plan', event.target.value)}>
                <option value="">{t('stores_planAny')}</option>
                <option value="none">{t('stores_planNone')}</option>
                {Array.from(new Map((page?.data ?? []).filter((row) => row.plan).map((row) => [row.plan!.code, row.plan!.name])).entries()).map(([code, name]) => (
                  <option key={code} value={code}>
                    {name}
                  </option>
                ))}
                {filters.plan && filters.plan !== 'none' && !(page?.data ?? []).some((row) => row.plan?.code === filters.plan) && <option value={filters.plan}>{filters.plan}</option>}
              </select>
            )}
          </Field>
          <Field label={t('stores_sort')}>
            {(control) => (
              <select {...control} className={fieldClass} value={filters.sort} onChange={(event) => set('sort', event.target.value as StoreFilters['sort'])}>
                {(['newest', 'name', 'debt', 'health'] as const).map((sort) => (
                  <option key={sort} value={sort}>
                    {t(`stores_sort_${sort}`)}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
        <div>
          <Button
            variant="secondary"
            onClick={() => {
              setSearch('');
              setFilters(DEFAULT_STORE_FILTERS);
            }}
          >
            {t('stores_clear')}
          </Button>
        </div>
      </section>

      {stores.isError ? (
        <InlineError title={t('stores_loadError')} message={errorText(stores.error, t)} retryLabel={t('retry')} retrying={stores.isFetching} onRetry={() => void stores.refetch()} />
      ) : !page ? (
        <CardSkeletons count={4} lines={3} label={t('loading')} />
      ) : page.data.length === 0 ? (
        <EmptyState icon={Store} title={t('stores_empty')} text={t('stores_emptyText')} />
      ) : (
        <div className="flex max-w-5xl flex-col gap-3">
          <p className="text-footnote text-fg-muted" aria-live="polite">
            {t('stores_total', { count: page.total })}
          </p>
          <ul aria-label={t('stores_listLabel')} className="flex flex-col gap-2">
            {page.data.map((row) => (
              <StoreListRow key={row.id} row={row} />
            ))}
          </ul>
          {page.last_page > 1 && (
            <div className="flex items-center justify-between gap-2">
              <Button variant="secondary" disabled={page.current_page <= 1} onClick={() => set('page', page.current_page - 1)}>
                {t('stores_prev')}
              </Button>
              <span className="text-footnote text-fg-muted">{t('stores_pageOf', { page: page.current_page, pages: page.last_page })}</span>
              <Button variant="secondary" disabled={page.current_page >= page.last_page} onClick={() => set('page', page.current_page + 1)}>
                {t('stores_next')}
              </Button>
            </div>
          )}
        </div>
      )}
    </>
  );
}

function StoreListRow({ row }: { row: StoreRow }) {
  const t = useT();
  const debt = Number(row.debt_balance);
  return (
    <li>
      <Card padded={false}>
        <Link
          href={`/stores/${row.id}`}
          aria-label={t('stores_open', { name: row.name })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">{row.name}</span>
              <Pill tone={statusTone(row.status)}>{row.status_label}</Pill>
              {row.health && (
                <Pill tone={HEALTH_TONE[row.health.level]}>
                  {row.health.label} · {t('stores_score', { score: row.health.score })}
                </Pill>
              )}
              {!row.is_active && row.status === 'active' && <Pill tone="warning">{t('stores_hidden')}</Pill>}
            </span>
            {row.health?.top_reason && row.health.level !== 'good' && <span className="break-words text-footnote text-fg">{row.health.top_reason}</span>}
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.region?.name ?? '—'}</span>
              <span>{t('stores_branches', { count: row.branches_count })}</span>
              <span>{row.plan ? `${row.plan.name} · ${row.plan.status_label}` : t('stores_noPlan')}</span>
              {debt > 0 && <span className={cx('font-bold', row.over_limit ? 'text-danger-soft-fg' : 'text-fg')}>{t('stores_debtValue', { amount: formatMoney(row.debt_balance, 'uz') })}</span>}
              {row.next_contact_on && <span>{t('stores_nextContact', { date: day(row.next_contact_on) })}</span>}
            </span>
            {(row.over_limit || row.is_premium || row.is_featured) && (
              <span className="flex flex-wrap gap-1.5">
                {row.over_limit && <Pill tone="danger">{t('stores_overLimitPill')}</Pill>}
                {row.is_premium && <Pill tone="info">{t('stores_premium')}</Pill>}
                {row.is_featured && <Pill tone="info">{t('stores_featured')}</Pill>}
              </span>
            )}
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </Link>
      </Card>
    </li>
  );
}
