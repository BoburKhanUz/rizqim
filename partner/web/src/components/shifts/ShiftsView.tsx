'use client';

import { useState } from 'react';
import { ArrowLeft, Printer, Timer } from 'lucide-react';
import { Button, Card, EmptyState, InlineError, Skeleton, formatDateTime, formatMoney, PageHeader, Pill } from '@rizqim/ui';
import { useShift, useShifts, useXReport, type Shift, type ShiftReport, type ShiftsQuery } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { useBranchScope } from '../shell/BranchScope';
import { labelFor } from '../reports/format';
import { HistoryFilters, SelectFilter, usableDates, type DateFilter } from '../reports/HistoryFilters';
import { DataTable, Td, Th } from '../reports/ui';
import { DifferenceFigure, ShiftReportView } from './ShiftReportView';

const STATUSES = ['open', 'closed'] as const;
const LIMIT = 50;

// /shifts: the shifts of a branch (or all) by business day, and one shift's X or Z report with the cash count, printable.
// Opening and closing a shift happen at the till; this only reads (pos.close_shift or reports.view).
export function ShiftsView() {
  const { t } = useI18n();
  const access = useCan();
  const [opened, setOpened] = useState<string | null>(null);

  if (access.ready && !access.can('pos.close_shift') && !access.can('reports.view')) {
    return (
      <>
        <PageHeader title={t('nav_shifts')} />
        <EmptyState icon={Timer} title={t('mgmt_forbidden')} text={t('shift_noAccess')} />
      </>
    );
  }

  return opened ? <ShiftDetailPage uuid={opened} onBack={() => setOpened(null)} /> : <ShiftList onOpen={setOpened} />;
}

function ShiftList({ onOpen }: { onOpen: (uuid: string) => void }) {
  const { t, language } = useI18n();
  const { branchId, branchName } = useBranchScope();
  const [status, setStatus] = useState('');
  const [dates, setDates] = useState<DateFilter>({ from: '', to: '' });
  const usable = usableDates(dates);
  const query: ShiftsQuery = {
    ...(branchId ? { branch_id: branchId } : {}),
    ...(status ? { status: status as (typeof STATUSES)[number] } : {}),
    ...(usable.from ? { from: usable.from } : {}),
    ...(usable.to ? { to: usable.to } : {}),
    limit: LIMIT,
  };
  const shifts = useShifts(query, { enabled: !usable.reversed });
  const money = (value: string) => formatMoney(value, language);

  return (
    <>
      <PageHeader title={t('nav_shifts')} subtitle={t('shift_subtitle')} />
      <Card className="flex flex-col gap-3">
        <HistoryFilters value={dates} reversed={usable.reversed} onChange={setDates}>
          <SelectFilter label={t('shift_status')} allLabel={t('shift_all')} value={status} onChange={setStatus} options={STATUSES.map((value) => ({ value, label: t(`shift_${value}`) }))} />
        </HistoryFilters>
        {shifts.isError && !shifts.data ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(shifts.error, t)} retryLabel={t('retry')} retrying={shifts.isFetching} onRetry={() => void shifts.refetch()} />
        ) : !shifts.data ? (
          <div role="status" aria-busy="true" className="flex flex-col gap-2">
            {[0, 1, 2, 3].map((n) => (
              <Skeleton key={n} className="h-10 w-full" />
            ))}
          </div>
        ) : shifts.data.length === 0 ? (
          <EmptyState icon={Timer} title={t('shift_empty')} text={t('shift_emptyText')} />
        ) : (
          <>
            <DataTable caption={t('nav_shifts')}>
              <thead>
                <tr>
                  <Th>{t('shift_z')}</Th>
                  <Th>{t('shift_status')}</Th>
                  <Th>{t('shift_branch')}</Th>
                  <Th>{t('shift_till')}</Th>
                  <Th>{t('shift_day')}</Th>
                  <Th>{t('shift_opened')}</Th>
                  <Th numeric>{t('shift_cashExpected')}</Th>
                  <Th numeric>{t('shift_cashCounted')}</Th>
                  <Th>{t('shift_cashDifference')}</Th>
                  <Th />
                </tr>
              </thead>
              <tbody>
                {shifts.data.map((shift: Shift) => (
                  <tr key={shift.uuid}>
                    <Td className="font-bold">{shift.z_number ?? '—'}</Td>
                    <Td>
                      <Pill tone={shift.status === 'open' ? 'success' : 'neutral'}>{labelFor(t, 'shift_', shift.status)}</Pill>
                      {shift.force_closed && <Pill className="ml-1" tone="warning">{t('shift_forced')}</Pill>}
                    </Td>
                    <Td>{branchName(shift.branch_id) ?? `#${shift.branch_id}`}</Td>
                    <Td>{shift.device?.name ?? '—'}</Td>
                    <Td className="whitespace-nowrap">{shift.business_date}</Td>
                    <Td muted className="whitespace-nowrap">
                      {formatDateTime(shift.opened_at, language)}
                      <span className="block text-caption">{shift.opened_by?.name ?? ''}</span>
                    </Td>
                    <Td numeric className="whitespace-nowrap">
                      {shift.expected_cash === null ? '—' : money(shift.expected_cash)}
                    </Td>
                    <Td numeric className="whitespace-nowrap">
                      {shift.counted_cash === null ? '—' : money(shift.counted_cash)}
                    </Td>
                    <Td>{shift.status === 'closed' ? <DifferenceFigure difference={shift.difference} language={language} /> : '—'}</Td>
                    <Td>
                      <Button variant="secondary" aria-label={t('shift_openAria', { day: shift.business_date, z: shift.z_number ?? '—' })} onClick={() => onOpen(shift.uuid)}>
                        {t('shift_report')}
                      </Button>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
            {shifts.data.length >= LIMIT && <p className="text-footnote text-fg-muted">{t('shift_limit', { n: LIMIT })}</p>}
          </>
        )}
      </Card>
    </>
  );
}

function ShiftDetailPage({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const { t } = useI18n();
  const { branchName } = useBranchScope();
  const detail = useShift(uuid);
  const closedWithZ = detail.data?.status === 'closed' && detail.data.summary !== null;
  // An open shift (or a closed one without a frozen summary) is read as an X report
  const xReport = useXReport(uuid, { enabled: detail.isSuccess && !closedWithZ });

  const shift: Shift | undefined = detail.data;
  const report: ShiftReport | undefined = closedWithZ ? (detail.data?.summary ?? undefined) : xReport.data?.report;
  const failed = detail.isError ? detail.error : xReport.isError ? xReport.error : null;

  return (
    <>
      <PageHeader
        title={t('nav_shifts')}
        subtitle={t('shift_subtitle')}
        action={
          <div className="flex gap-2 print:hidden">
            <Button variant="secondary" icon={ArrowLeft} onClick={onBack}>
              {t('shift_back')}
            </Button>
            <Button icon={Printer} onClick={() => window.print()} disabled={!report}>
              {t('shift_print')}
            </Button>
          </div>
        }
      />
      {failed ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(failed, t)} retryLabel={t('retry')} retrying={detail.isFetching || xReport.isFetching} onRetry={() => void (detail.isError ? detail.refetch() : xReport.refetch())} />
      ) : !shift || !report ? (
        <div role="status" aria-busy="true" className="flex flex-col gap-3">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <ShiftReportView shift={shift} report={report} branchName={branchName(shift.branch_id)} />
      )}
    </>
  );
}
