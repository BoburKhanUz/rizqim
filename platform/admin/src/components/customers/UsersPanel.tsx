'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { ChevronRight, Search, UsersRound } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, InlineError, Pill } from '@rizqim/ui';
import { DEFAULT_USER_FILTERS, ROLE_FILTERS, useUsers, type UserFilters, type UserRow } from '@/lib/api/customers';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { ROLE_LABEL } from './customersModel';

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
export const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');
export { CHIP };

// Foydalanuvchilar: customers, couriers and store staff, newest first, a page at a time. Search by name, or by phone: without
// pii.view only the whole number finds anybody (a masked number cannot be read digit by digit). A row opens the user card.
export function UsersPanel() {
  const t = useT();
  const { can } = useCan();
  const [filters, setFilters] = useState<UserFilters>(DEFAULT_USER_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const users = useUsers(filters);
  const rows = useMemo(() => users.data?.pages.flatMap((page) => page.data) ?? [], [users.data]);
  const set = <K extends keyof UserFilters>(name: K, value: UserFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div role="group" aria-label={t('customers_roleFilter')} className="flex flex-wrap gap-2">
        {ROLE_FILTERS.map((role) => (
          <button key={role || 'all'} type="button" aria-pressed={filters.role === role} className={cx(CHIP, chipTone(filters.role === role))} onClick={() => set('role', role)}>
            {t(ROLE_LABEL[role])}
          </button>
        ))}
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('customers_search')} hint={can('pii.view') ? t('customers_searchHintPii') : t('customers_searchHint')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('customers_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
        <Field label={t('customers_status')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.status} onChange={(event) => set('status', event.target.value as UserFilters['status'])}>
              <option value="">{t('customers_statusAll')}</option>
              <option value="active">{t('customers_statusActive')}</option>
              <option value="blocked">{t('customers_statusBlocked')}</option>
            </select>
          )}
        </Field>
      </div>

      {users.isError ? (
        <InlineError title={t('customers_loadError')} message={errorText(users.error, t)} retryLabel={t('retry')} retrying={users.isFetching} onRetry={() => void users.refetch()} />
      ) : !users.data ? (
        <CardSkeletons count={4} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={UsersRound} title={t('customers_usersEmpty')} text={filters.q ? t('customers_usersEmptySearch') : undefined} />
      ) : (
        <>
          <ul aria-label={t('customers_usersLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <UserListRow key={row.id} row={row} />
            ))}
          </ul>
          {users.hasNextPage && (
            <Button variant="secondary" loading={users.isFetchingNextPage} onClick={() => void users.fetchNextPage()}>
              {t('customers_loadMore')}
            </Button>
          )}
        </>
      )}
    </div>
  );
}

function UserListRow({ row }: { row: UserRow }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <Link
          href={`/users/${row.id}`}
          aria-label={t('customers_openUser', { name: row.name })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">{row.name}</span>
              <Pill tone="neutral">{row.role_label}</Pill>
              {row.blocked && <Pill tone="danger">{t('customers_blocked')}</Pill>}
            </span>
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.phone ?? t('customers_noPhone')}</span>
              {row.store && <span className="break-words">{row.store.name}</span>}
              <span>{t('customers_ordersCount', { count: row.orders_count })}</span>
              <span>{t(row.telegram_linked ? 'customers_telegramYes' : 'customers_telegramNo')}</span>
              <span>{t('customers_since', { date: when(row.created_at) })}</span>
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </Link>
      </Card>
    </li>
  );
}
