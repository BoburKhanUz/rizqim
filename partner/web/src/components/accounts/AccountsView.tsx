'use client';

import { useState } from 'react';
import { BookUser, Pencil, Plus, ScrollText, Search } from 'lucide-react';
import { Button, Card, EmptyState, Field, InlineError, Skeleton, cx, fieldClass, formatMoney, PageHeader, Notice, Pill } from '@rizqim/ui';
import { useCustomerAccounts, useDebtors, type CustomerAccount } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DataTable, Pager, Td, Th } from '../reports/ui';
import { AccountDialog } from './AccountDialog';
import { StatementDialog } from './StatementDialog';

const PER_PAGE = 30;

// The words for a credit limit: no limit, no credit at all, or the sum
export function limitText(account: Pick<CustomerAccount, 'credit_limit'>, money: (value: string) => string, t: ReturnType<typeof useI18n>['t']): string {
  if (account.credit_limit === null) return t('acct_limitNone');
  return Number(account.credit_limit) === 0 ? t('acct_limitZero') : money(account.credit_limit);
}

// /accounts: customer accounts (nasiya). Who owes what, the statement of an account, and the credit limit and block. A debt is
// changed only by sales on credit, repayments at the till and a manager's adjustment: nothing here sets a balance.
export function AccountsView() {
  const { t, language } = useI18n();
  const access = useCan();
  const [tab, setTab] = useState<'all' | 'debtors'>('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [dialog, setDialog] = useState<{ account: CustomerAccount | null } | null>(null);
  const [statement, setStatement] = useState<CustomerAccount | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const debtors = useDebtors();
  const q = search.trim();
  const list = useCustomerAccounts({ ...(q ? { q } : {}), ...(tab === 'debtors' ? { debtors: true } : {}), per_page: PER_PAGE, page });
  const money = (value: string) => formatMoney(value, language);

  if (access.ready && !access.can('customer_accounts.manage')) {
    return (
      <>
        <PageHeader title={t('nav_accounts')} />
        <EmptyState icon={BookUser} title={t('mgmt_forbidden')} text={t('acct_noAccess')} />
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t('nav_accounts')}
        subtitle={t('acct_subtitle')}
        action={
          <Button icon={Plus} onClick={() => setDialog({ account: null })}>
            {t('acct_add')}
          </Button>
        }
      />
      <Notice message={notice} />

      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('acct_totalDebt')}</span>
          {debtors.data ? <span className="text-display font-extrabold text-fg">{money(debtors.data.total_debt)}</span> : debtors.isError ? <span className="text-footnote text-danger-soft-fg">{errorText(debtors.error, t)}</span> : <Skeleton className="h-9 w-40" />}
        </Card>
        <Card className="flex flex-col gap-1">
          <span className="text-footnote font-bold text-fg-muted">{t('acct_debtorsCount')}</span>
          {debtors.data ? <span className="text-display font-extrabold text-fg">{debtors.data.count}</span> : <Skeleton className="h-9 w-20" />}
        </Card>
      </div>

      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div role="group" aria-label={t('acct_filter')} className="flex gap-1 rounded-control border border-line bg-surface p-1">
            {(['all', 'debtors'] as const).map((id) => (
              <button
                key={id}
                type="button"
                aria-pressed={tab === id}
                onClick={() => {
                  setTab(id);
                  setPage(1);
                }}
                className={cx('min-h-11 rounded-control px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-brand', tab === id ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:bg-surface-2')}
              >
                {t(`acct_tab_${id}`)}
              </button>
            ))}
          </div>
          <Field label={t('acct_search')}>
            {(control) => (
              <div className="relative">
                <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                <input
                  {...control}
                  type="search"
                  value={search}
                  onChange={(event) => {
                    setSearch(event.target.value);
                    setPage(1);
                  }}
                  className={`${fieldClass} pl-9 sm:w-72`}
                />
              </div>
            )}
          </Field>
        </div>

        {list.isError && !list.data ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(list.error, t)} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />
        ) : !list.data ? (
          <div role="status" aria-busy="true" className="flex flex-col gap-2">
            {[0, 1, 2, 3].map((n) => (
              <Skeleton key={n} className="h-10 w-full" />
            ))}
          </div>
        ) : list.data.accounts.length === 0 ? (
          <EmptyState icon={BookUser} title={q || tab === 'debtors' ? t('acct_noMatch') : t('acct_empty')} text={q || tab === 'debtors' ? t('acct_noMatchText') : t('acct_emptyText')} />
        ) : (
          <div aria-busy={list.isPlaceholderData || undefined} className={list.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
            <DataTable caption={t('nav_accounts')}>
              <thead>
                <tr>
                  <Th>{t('acct_name')}</Th>
                  <Th>{t('acct_group')}</Th>
                  <Th>{t('acct_phone')}</Th>
                  <Th numeric>{t('acct_balance')}</Th>
                  <Th numeric>{t('acct_limit')}</Th>
                  <Th numeric>{t('acct_available')}</Th>
                  <Th>{t('acct_state')}</Th>
                  <Th>{t('mgmt_actions')}</Th>
                </tr>
              </thead>
              <tbody>
                {list.data.accounts.map((account) => {
                  const owes = Number(account.balance) > 0;
                  return (
                    <tr key={account.uuid}>
                      <Td className="font-bold">{account.name}</Td>
                      <Td muted>{account.group_name ?? '—'}</Td>
                      <Td muted className="whitespace-nowrap">
                        {account.phone ?? '—'}
                      </Td>
                      <Td numeric className={cx('whitespace-nowrap font-bold', owes && 'text-danger-soft-fg')}>
                        {money(account.balance)}
                      </Td>
                      <Td numeric className="whitespace-nowrap">
                        {limitText(account, money, t)}
                      </Td>
                      <Td numeric muted className="whitespace-nowrap">
                        {account.available_credit === null ? '—' : money(account.available_credit)}
                      </Td>
                      <Td>
                        {account.is_blocked ? <Pill tone="danger">{t('acct_blocked')}</Pill> : owes ? <Pill tone="warning">{t('acct_owes')}</Pill> : <Pill tone="success">{t('acct_clear')}</Pill>}
                      </Td>
                      <Td>
                        <div className="flex gap-1">
                          <Button variant="secondary" icon={ScrollText} aria-label={t('acct_statementAria', { name: account.name })} onClick={() => setStatement(account)}>
                            {t('acct_statement')}
                          </Button>
                          <Button variant="secondary" icon={Pencil} aria-label={t('acct_editAria', { name: account.name })} onClick={() => setDialog({ account })}>
                            {t('acct_edit')}
                          </Button>
                        </div>
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </DataTable>
            <Pager page={list.data.meta.current_page || page} lastPage={list.data.meta.last_page} total={list.data.meta.total} busy={list.isFetching} onPage={setPage} />
          </div>
        )}
      </Card>

      {dialog && (
        <AccountDialog
          account={dialog.account}
          onClose={() => setDialog(null)}
          onSaved={(saved) => {
            setNotice(dialog.account ? t('acct_saved', { name: saved.name }) : t('acct_created', { name: saved.name }));
            setDialog(null);
          }}
        />
      )}
      {statement && <StatementDialog account={statement} onClose={() => setStatement(null)} />}
    </>
  );
}
