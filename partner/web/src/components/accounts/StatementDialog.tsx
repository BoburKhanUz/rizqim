'use client';

import { useState } from 'react';
import { Button, Dialog, InlineError, Skeleton, formatDateTime, formatMoney } from '@rizqim/ui';
import { useAccountStatement, type CustomerAccount } from '@rizqim/staff';
import { addDays, todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { DateField } from '../pos/fields';
import { DataTable, Td, Th } from '../reports/ui';

// The statement of an account: the debt at the start and the end of a period, the totals by kind and every entry.
export function StatementDialog({ account, onClose }: { account: CustomerAccount; onClose: () => void }) {
  const { t, language } = useI18n();
  const today = todayIn('Asia/Tashkent');
  const [from, setFrom] = useState(addDays(today, -30));
  const [to, setTo] = useState(today);
  const range = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const reversed = from !== '' && to !== '' && from > to;
  const statement = useAccountStatement(account.uuid, range, { enabled: !reversed });
  const money = (value: string) => formatMoney(value, language);
  const data = statement.data;

  return (
    <Dialog
      open
      title={t('acct_statementTitle', { name: account.name })}
      closeLabel={t('mgmt_close')}
      onClose={onClose}
      footer={
        <Button variant="secondary" onClick={onClose}>
          {t('mgmt_close')}
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('report_from')} value={from} onChange={setFrom} />
          <DateField label={t('report_to')} value={to} onChange={setTo} />
        </div>
        {reversed && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {t('report_problem_reversed')}
          </p>
        )}
        {!data ? (
          statement.isError ? (
            <InlineError title={t('loadErrorTitle')} message={errorText(statement.error, t)} retryLabel={t('retry')} retrying={statement.isFetching} onRetry={() => void statement.refetch()} />
          ) : (
            <Skeleton className="h-40 w-full" />
          )
        ) : (
          <>
            <dl className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-body">
              <dt className="text-fg-muted">{t('acct_st_opening')}</dt>
              <dd className="text-right font-bold">{money(data.opening_balance)}</dd>
              <dt className="text-fg-muted">{t('acct_st_charges')}</dt>
              <dd className="text-right">{money(data.charges_total)}</dd>
              <dt className="text-fg-muted">{t('acct_st_repayments')}</dt>
              <dd className="text-right">{money(data.repayments_total)}</dd>
              <dt className="text-fg-muted">{t('acct_st_adjustments')}</dt>
              <dd className="text-right">{money(data.adjustments_total)}</dd>
              <dt className="font-bold text-fg">{t('acct_st_closing')}</dt>
              <dd className="text-right text-headline font-extrabold">{money(data.closing_balance)}</dd>
            </dl>
            {data.entries.length === 0 ? (
              <p className="text-footnote text-fg-muted">{t('acct_st_empty')}</p>
            ) : (
              <DataTable caption={t('acct_statement')}>
                <thead>
                  <tr>
                    <Th>{t('fin_colDate')}</Th>
                    <Th>{t('fin_colType')}</Th>
                    <Th numeric>{t('fin_colAmount')}</Th>
                    <Th numeric>{t('acct_st_after')}</Th>
                    <Th>{t('fin_colNotes')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.entries.map((entry) => (
                    <tr key={entry.uuid}>
                      <Td muted className="whitespace-nowrap">
                        {entry.created_at ? formatDateTime(entry.created_at, language) : '—'}
                      </Td>
                      <Td>{entry.type_label}</Td>
                      <Td numeric className="whitespace-nowrap font-bold">
                        {Number(entry.amount) > 0 ? '+' : ''}
                        {money(entry.amount)}
                      </Td>
                      <Td numeric muted className="whitespace-nowrap">
                        {money(entry.balance_after)}
                      </Td>
                      <Td muted className="max-w-56 break-words">
                        {[entry.user?.name, entry.note].filter(Boolean).join(' · ') || '—'}
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}
          </>
        )}
      </div>
    </Dialog>
  );
}
