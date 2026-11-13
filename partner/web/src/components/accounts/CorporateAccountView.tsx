'use client';

import { useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, Building2, Download, ListPlus, Pencil, Plus, Printer, Search, UserPlus } from 'lucide-react';
import { Button, Card, EmptyState, Field, InlineError, Notice, PageHeader, Pill, Skeleton, Tabs, cx, fieldClass, formatMoney } from '@rizqim/ui';
import {
  useCorporateMembers,
  useCorporateStatement,
  useCustomerAccount,
  useDownloadCorporateStatement,
  useUpdateCorporateMember,
  type CorporateMember,
  type CorporateStatement,
  type CustomerAccount,
} from '@rizqim/staff';
import { todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { saveBlob } from '../reports/download';
import { DataTable, Td, Th } from '../reports/ui';
import { AccountDialog } from './AccountDialog';
import { BulkMembersDialog, MemberDialog } from './MemberDialogs';

type Tab = 'members' | 'statement';

// /accounts/[uuid]: a company's account (P6-44). Its people with their talon codes and limits (add one, paste a list, turn a
// talon off, give a new code), and the monthly statement: what each member ate on each business day, the totals, the Excel
// file and a printable invoice summary. Editing needs customer_accounts.manage, the statement reports.view.
export function CorporateAccountView({ uuid }: { uuid: string }) {
  const { t, language } = useI18n();
  const access = useCan();
  const detail = useCustomerAccount(uuid);
  const [tab, setTab] = useState<Tab>('members');
  const [editing, setEditing] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const money = (value: string) => formatMoney(value, language);
  const account = detail.data?.account;

  if (access.ready && !access.can('customer_accounts.manage')) {
    return (
      <>
        <PageHeader title={t('nav_accounts')} />
        <EmptyState icon={Building2} title={t('mgmt_forbidden')} text={t('acct_noAccess')} />
      </>
    );
  }

  const back = (
    <Link href="/accounts" className="mb-3 inline-flex min-h-11 items-center gap-2 text-body font-bold text-brand print:hidden">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('corp_back')}
    </Link>
  );

  if (!account) {
    return (
      <>
        {back}
        {detail.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(detail.error, t)} retryLabel={t('retry')} retrying={detail.isFetching} onRetry={() => void detail.refetch()} />
        ) : (
          <Skeleton className="h-40 w-full" />
        )}
      </>
    );
  }

  if (account.kind !== 'corporate') {
    return (
      <>
        {back}
        <EmptyState icon={Building2} title={account.name} text={t('corp_notCorporate')} />
      </>
    );
  }

  const subtitle = [account.company_name, account.inn ? `${t('corp_inn')} ${account.inn}` : null].filter(Boolean).join(' · ');

  return (
    <>
      {back}
      <PageHeader
        title={account.name}
        {...(subtitle ? { subtitle } : {})}
        action={
          <Button variant="secondary" icon={Pencil} onClick={() => setEditing(true)} className="print:hidden">
            {t('acct_edit')}
          </Button>
        }
      />
      <Notice message={notice} />
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 print:hidden">
        <Summary label={t('corp_balance')} value={money(account.balance)} strong />
        <Summary label={t('corp_monthlyLimit')} value={account.monthly_limit ? money(account.monthly_limit) : t('corp_noMonthlyLimit')} />
        <Summary label={t('corp_activeMembers')} value={String(account.members_count ?? 0)} />
        <Summary label={t('corp_talonMode')} value={t(`corp_talon_${account.talon_mode ?? 'none'}` as const)} note={account.charge_approval ? t('corp_approvalOn') : t('corp_approvalOff')} />
      </div>

      <div className="print:hidden">
        <Tabs
          idPrefix="corp"
          label={t('corp_tabs')}
          value={tab}
          onChange={setTab}
          tabs={[
            { id: 'members', label: t('corp_tab_members') },
            { id: 'statement', label: t('corp_tab_statement') },
          ]}
        />
      </div>
      <div role="tabpanel" id={`corp-panel-${tab}`} aria-labelledby={`corp-tab-${tab}`}>
        {tab === 'members' ? (
          <MembersPanel account={account} onNotice={setNotice} />
        ) : access.ready && !access.can('reports.view') ? (
          <EmptyState icon={Building2} title={t('mgmt_forbidden')} text={t('corp_st_forbidden')} />
        ) : (
          <StatementPanel account={account} />
        )}
      </div>

      {editing && (
        <AccountDialog
          account={account}
          onClose={() => setEditing(false)}
          onSaved={(saved) => {
            setNotice(t('acct_saved', { name: saved.name }));
            setEditing(false);
          }}
        />
      )}
    </>
  );
}

function Summary({ label, value, note, strong }: { label: string; value: string; note?: string; strong?: boolean }) {
  return (
    <Card className="flex flex-col gap-1">
      <span className="text-footnote font-bold text-fg-muted">{label}</span>
      <span className={cx('font-extrabold text-fg', strong ? 'text-display' : 'text-headline')}>{value}</span>
      {note && <span className="text-footnote text-fg-muted">{note}</span>}
    </Card>
  );
}

function MembersPanel({ account, onNotice }: { account: CustomerAccount; onNotice: (text: string) => void }) {
  const { t, language } = useI18n();
  const [search, setSearch] = useState('');
  const [dialog, setDialog] = useState<{
    member: CorporateMember | null;
  } | null>(null);
  const [bulk, setBulk] = useState(false);
  const q = search.trim();
  const members = useCorporateMembers(account.uuid, q ? { q } : {});
  const toggle = useUpdateCorporateMember(account.uuid, {
    onSuccess: (member) => onNotice(t('corp_memberSaved', { name: member.name })),
    onError: (error) => onNotice(errorText(error, t)),
  });
  const money = (value: string) => formatMoney(value, language);
  const list = members.data?.members ?? [];

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <Field label={t('corp_search')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} onChange={(event) => setSearch(event.target.value)} className={`${fieldClass} pl-9 sm:w-72`} />
            </div>
          )}
        </Field>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" icon={ListPlus} onClick={() => setBulk(true)}>
            {t('corp_bulk')}
          </Button>
          <Button icon={UserPlus} onClick={() => setDialog({ member: null })}>
            {t('corp_addMember')}
          </Button>
        </div>
      </div>

      {members.isError && !members.data ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(members.error, t)} retryLabel={t('retry')} retrying={members.isFetching} onRetry={() => void members.refetch()} />
      ) : !members.data ? (
        <div role="status" aria-busy="true" className="flex flex-col gap-2">
          {[0, 1, 2].map((n) => (
            <Skeleton key={n} className="h-10 w-full" />
          ))}
        </div>
      ) : list.length === 0 ? (
        <EmptyState icon={Plus} title={q ? t('acct_noMatch') : t('corp_membersEmpty')} text={q ? t('acct_noMatchText') : t('corp_membersEmptyText')} />
      ) : (
        <DataTable caption={t('corp_tab_members')}>
          <thead>
            <tr>
              <Th>{t('corp_col_name')}</Th>
              <Th>{t('acct_phone')}</Th>
              <Th>{t('corp_col_code')}</Th>
              <Th numeric>{t('corp_col_daily')}</Th>
              <Th numeric>{t('corp_col_meals')}</Th>
              <Th>{t('acct_state')}</Th>
              <Th>{t('mgmt_actions')}</Th>
            </tr>
          </thead>
          <tbody>
            {list.map((member) => (
              <tr key={member.uuid}>
                <Td className="font-bold">{member.name}</Td>
                <Td muted className="whitespace-nowrap">
                  {member.phone ?? '—'}
                </Td>
                <Td className="whitespace-nowrap font-mono">{member.code}</Td>
                <Td numeric className="whitespace-nowrap">
                  {member.daily_limit ? money(member.daily_limit) : t('corp_noLimit')}
                </Td>
                <Td numeric>{member.meals_per_day ?? '—'}</Td>
                <Td>{member.is_active ? <Pill tone="success">{t('corp_active')}</Pill> : <Pill tone="neutral">{t('corp_inactive')}</Pill>}</Td>
                <Td>
                  <div className="flex flex-wrap gap-1">
                    <Button variant="secondary" icon={Pencil} aria-label={t('corp_editAria', { name: member.name })} onClick={() => setDialog({ member })}>
                      {t('acct_edit')}
                    </Button>
                    <Button
                      variant="secondary"
                      aria-label={member.is_active ? t('corp_deactivateAria', { name: member.name }) : t('corp_activateAria', { name: member.name })}
                      loading={toggle.isPending && toggle.variables?.member === member.uuid}
                      onClick={() =>
                        toggle.mutate({
                          member: member.uuid,
                          patch: { is_active: !member.is_active },
                        })
                      }
                    >
                      {member.is_active ? t('corp_deactivate') : t('corp_activate')}
                    </Button>
                  </div>
                </Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      )}

      {dialog && (
        <MemberDialog
          accountUuid={account.uuid}
          member={dialog.member}
          onClose={() => setDialog(null)}
          onSaved={(member, created) => {
            onNotice(
              created
                ? t('corp_memberAdded', {
                    name: member.name,
                    code: member.code,
                  })
                : t('corp_memberSaved', { name: member.name }),
            );
            setDialog(null);
          }}
        />
      )}
      {bulk && (
        <BulkMembersDialog
          accountUuid={account.uuid}
          onClose={() => setBulk(false)}
          onImported={(count) => {
            onNotice(t('corp_imported', { n: count }));
            setBulk(false);
          }}
        />
      )}
    </Card>
  );
}

const dayOf = (date: string) => String(Number(date.slice(8, 10)));

function StatementPanel({ account }: { account: CustomerAccount }) {
  const { t, language } = useI18n();
  const [month, setMonth] = useState(todayIn('Asia/Tashkent').slice(0, 7));
  const [fileNotice, setFileNotice] = useState<{
    tone: 'ok' | 'error';
    text: string;
  } | null>(null);
  const statement = useCorporateStatement(account.uuid, month || null);
  const download = useDownloadCorporateStatement(account.uuid, {
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setFileNotice({
        tone: 'ok',
        text: t('corp_exported', { name: filename }),
      });
    },
    onError: () => setFileNotice({ tone: 'error', text: t('corp_exportFailed') }),
  });
  const money = (value: string) => formatMoney(value, language);
  const data = statement.data;

  return (
    <div className="flex flex-col gap-4">
      <Card className="flex flex-wrap items-end justify-between gap-3 print:hidden">
        <Field label={t('corp_month')}>
          {(props) => <input {...props} type="month" value={month} onChange={(event) => setMonth(event.target.value)} className={cx(fieldClass, 'w-auto min-w-44')} />}
        </Field>
        <div className="flex flex-col items-end gap-2">
          <div className="flex flex-wrap gap-2">
            <Button
              variant="secondary"
              icon={Download}
              loading={download.isPending}
              disabled={!month}
              onClick={() => {
                setFileNotice(null);
                download.mutate(month);
              }}
            >
              {download.isPending ? t('corp_exporting') : t('corp_export')}
            </Button>
            <Button variant="secondary" icon={Printer} disabled={!data} onClick={() => window.print()}>
              {t('corp_print')}
            </Button>
          </div>
          {fileNotice && (
            <p role={fileNotice.tone === 'error' ? 'alert' : 'status'} className={cx('text-footnote font-bold', fileNotice.tone === 'error' ? 'text-danger-soft-fg' : 'text-success-soft-fg')}>
              {fileNotice.text}
            </p>
          )}
        </div>
      </Card>

      {!data ? (
        statement.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(statement.error, t)} retryLabel={t('retry')} retrying={statement.isFetching} onRetry={() => void statement.refetch()} />
        ) : (
          <Skeleton className="h-40 w-full" />
        )
      ) : (
        <>
          <Invoice data={data} money={money} />
          <StatementTable data={data} money={money} />
        </>
      )}
    </div>
  );
}

// The invoice summary: printed as it is on the screen (the menu, the tabs and the buttons are left out)
function Invoice({ data, money }: { data: CorporateStatement; money: (value: string) => string }) {
  const { t } = useI18n();
  const rows: Array<[string, string]> = [
    [t('corp_companyName'), data.account.company_name ?? data.account.name],
    [t('corp_inn'), data.account.inn ?? '—'],
    [t('corp_contactName'), [data.account.contact_name, data.account.contact_phone].filter(Boolean).join(' · ') || '—'],
    [t('corp_invoiceEmail'), data.account.invoice_email ?? '—'],
    [t('corp_period'), `${data.from} — ${data.to}`],
  ];
  return (
    <section aria-label={t('corp_invoiceTitle', { month: data.month })}>
      <Card className="flex flex-col gap-3 break-inside-avoid print:border-fg-subtle print:shadow-none">
        <h2 className="text-headline font-bold text-fg">{t('corp_invoiceTitle', { month: data.month })}</h2>
        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-body">
          {rows.map(([label, value]) => (
            <div key={label} className="contents">
              <dt className="text-fg-muted">{label}</dt>
              <dd className="font-bold text-fg">{value}</dd>
            </div>
          ))}
        </dl>
        <dl className="grid gap-3 sm:grid-cols-4 print:grid-cols-4">
          {[
            [t('corp_st_total'), money(data.total)],
            [t('corp_st_meals'), String(data.meals)],
            [t('corp_st_repaid'), money(data.repaid_total)],
            [t('corp_st_balance'), money(data.balance)],
          ].map(([label, value]) => (
            <div key={label} className="flex flex-col gap-1 rounded-control bg-surface-2 p-3 print:bg-transparent">
              <dt className="text-footnote font-bold text-fg-muted">{label}</dt>
              <dd className="text-headline font-extrabold text-fg">{value}</dd>
            </div>
          ))}
        </dl>
        {data.monthly_limit && (
          <p className="text-footnote text-fg-muted">
            {t('corp_st_monthlyLimit')}: {money(data.monthly_limit)}
          </p>
        )}
      </Card>
    </section>
  );
}

// Members by business day: only the days something was eaten get a column
function StatementTable({ data, money }: { data: CorporateStatement; money: (value: string) => string }) {
  const { t } = useI18n();
  const days = data.day_totals.map((cell) => cell.date);
  if (days.length === 0) return <EmptyState icon={Building2} title={t('corp_st_empty')} text={t('corp_st_byDay')} />;
  const rows = data.rows.filter((row) => row.cells.length > 0 || row.member?.is_active);
  return (
    <Card className="flex flex-col gap-2 print:border-fg-subtle print:shadow-none">
      <h2 className="text-footnote font-bold text-fg-muted">{t('corp_st_byDay')}</h2>
      <DataTable caption={t('corp_tab_statement')}>
        <thead>
          <tr>
            <Th>{t('corp_col_name')}</Th>
            {days.map((date) => (
              <Th key={date} numeric>
                {dayOf(date)}
              </Th>
            ))}
            <Th numeric>{t('corp_st_amount')}</Th>
            <Th numeric>{t('corp_st_meals')}</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const cells = new Map(row.cells.map((cell) => [cell.date, cell]));
            return (
              <tr key={row.member?.uuid ?? 'none'}>
                <Td className="whitespace-nowrap font-bold">{row.member?.name ?? t('corp_st_noMember')}</Td>
                {days.map((date) => {
                  const cell = cells.get(date);
                  return (
                    <Td key={date} numeric muted={!cell || Number(cell.amount) === 0} className="whitespace-nowrap">
                      {cell && Number(cell.amount) !== 0 ? money(cell.amount) : '—'}
                    </Td>
                  );
                })}
                <Td numeric className="whitespace-nowrap font-bold">
                  {money(row.amount)}
                </Td>
                <Td numeric>{row.meals}</Td>
              </tr>
            );
          })}
          <tr>
            <Td className="font-extrabold">{t('corp_st_totalRow')}</Td>
            {data.day_totals.map((cell) => (
              <Td key={cell.date} numeric className="whitespace-nowrap font-bold">
                {money(cell.amount)}
              </Td>
            ))}
            <Td numeric className="whitespace-nowrap font-extrabold">
              {money(data.total)}
            </Td>
            <Td numeric className="font-bold">
              {data.meals}
            </Td>
          </tr>
        </tbody>
      </DataTable>
    </Card>
  );
}
