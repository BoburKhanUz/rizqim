import { BadgeCheck, Clock, Lock } from 'lucide-react';
import { useBranchShiftSummary, type Localized, type Shift, type ShiftReport } from '@rizqim/staff';
import { useWorkBranch } from '../hooks/useWorkBranch';
import { useStaffText } from '../hooks/useStaffText';
import { Card, CardSkeletons, EmptyState, InlineError, PageHeader, Section } from '../components/ui';
import { BranchSelect } from '../components/staff/BranchSelect';
import { Pill } from '../components/staff/Pill';
import { getApiErrorMessage } from '../lib/apiError';
import { formatDate, formatDateTime, formatMoney } from '../lib/format';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';

const TEXT = {
  title: { uz: 'Smena xulosasi', ru: 'Итог смены' },
  subtitle: { uz: "Ochiq smena va oxirgi Z hisobot", ru: 'Открытая смена и последний Z-отчёт' },
  branch: { uz: 'Filial', ru: 'Филиал' },
  open: { uz: 'Ochiq smena', ru: 'Открытая смена' },
  openedBy: { uz: 'Ochdi: {{by}}, {{time}}', ru: 'Открыл: {{by}}, {{time}}' },
  expected: { uz: "Kassada bo'lishi kerak", ru: 'Должно быть в кассе' },
  sales: { uz: 'Sof tushum', ru: 'Чистая выручка' },
  bills: { uz: 'Schyotlar', ru: 'Счета' },
  cash: { uz: 'Naqd', ru: 'Наличные' },
  card: { uz: 'Karta', ru: 'Карта' },
  qr: { uz: 'QR', ru: 'QR' },
  prepayment: { uz: 'Avans hisobidan', ru: 'Из аванса' },
  account: { uz: 'Nasiyaga', ru: 'В долг' },
  discount: { uz: 'Chegirma', ru: 'Скидки' },
  voids: { uz: "O'chirilgan taomlar", ru: 'Удалённые блюда' },
  refunds: { uz: 'Qaytarishlar', ru: 'Возвраты' },
  deposits: { uz: 'Olingan avans', ru: 'Принятые авансы' },
  repayments: { uz: "Qarz to'lovlari", ru: 'Погашения долга' },
  noOpen: { uz: "Hozir ochiq smena yo'q", ru: 'Сейчас нет открытой смены' },
  noOpenText: { uz: 'Smenani kassada kassir ochadi.', ru: 'Смену на кассе открывает кассир.' },
  lastZ: { uz: 'Oxirgi Z hisobot', ru: 'Последний Z-отчёт' },
  zTitle: { uz: 'Z № {{n}}', ru: 'Z № {{n}}' },
  noZ: { uz: "Yopilgan smena hali yo'q", ru: 'Закрытых смен ещё нет' },
  counted: { uz: 'Sanalgan naqd', ru: 'Пересчитано' },
  diff: { uz: 'Farq', ru: 'Разница' },
  diffZero: { uz: "Farq yo'q", ru: 'Разницы нет' },
  diffShort: { uz: 'Kam: {{amount}}', ru: 'Недостача: {{amount}}' },
  diffOver: { uz: 'Ortiqcha: {{amount}}', ru: 'Излишек: {{amount}}' },
  forced: { uz: 'Majburan yopilgan', ru: 'Закрыта принудительно' },
  closedLine: { uz: 'Yopdi: {{by}}, {{time}}', ru: 'Закрыл: {{by}}, {{time}}' },
  forbidden: { uz: "Smenalarni ko'rishga ruxsatingiz yo'q.", ru: 'У вас нет доступа к сменам.' },
} satisfies Record<string, Localized>;

type Tx = ReturnType<typeof useStaffText<typeof TEXT>>;

function Figure({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex items-baseline justify-between gap-3 py-1">
      <dt className="text-footnote text-fg-muted">{label}</dt>
      <dd className={strong ? 'text-headline font-extrabold text-fg' : 'text-body font-bold text-fg'}>{value}</dd>
    </div>
  );
}

// The difference of the cash count in words (the colour backs the sign up)
function Difference({ difference, tx }: { difference: string | null; tx: Tx }) {
  if (difference === null) return <span className="text-fg-muted">—</span>;
  const value = Number(difference);
  if (value === 0) return <Pill tone="success">{tx('diffZero')}</Pill>;
  return value < 0 ? <Pill tone="danger">{tx('diffShort', { amount: formatMoney(Math.abs(value)) })}</Pill> : <Pill tone="warning">{tx('diffOver', { amount: formatMoney(value) })}</Pill>;
}

function OpenShiftCard({ shift, report, tx }: { shift: Shift; report: ShiftReport; tx: Tx }) {
  const tenders = Object.fromEntries(report.by_tender.map((row) => [row.tender, row.amount]));
  return (
    <Card className="flex flex-col gap-2" role="group" aria-label={`${tx('open')}: ${shift.device?.name ?? ''}`}>
      <div className="flex items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
          <Clock aria-hidden="true" className="size-5 text-success-soft-fg" />
          {shift.device?.name ?? tx('open')}
        </h2>
        <Pill tone="success">{tx('open')}</Pill>
      </div>
      <p className="text-footnote text-fg-muted">{tx('openedBy', { by: shift.opened_by?.name ?? '—', time: formatDateTime(shift.opened_at) })}</p>
      <div>
        <p className="text-footnote text-fg-muted">{tx('expected')}</p>
        <p className="text-display font-extrabold text-brand">{formatMoney(report.cash.expected)}</p>
      </div>
      <dl className="divide-y divide-line">
        <Figure label={tx('sales')} value={formatMoney(report.sales.net)} strong />
        <Figure label={tx('bills')} value={String(report.sales.bills)} />
        <Figure label={tx('cash')} value={formatMoney(tenders.cash)} />
        <Figure label={tx('card')} value={formatMoney(tenders.card)} />
        <Figure label={tx('qr')} value={formatMoney(tenders.qr)} />
        <Figure label={tx('prepayment')} value={formatMoney(tenders.prepayment)} />
        <Figure label={tx('account')} value={formatMoney(tenders.account)} />
        <Figure label={tx('discount')} value={formatMoney(report.discounts.amount)} />
        <Figure label={tx('voids')} value={formatMoney(report.voids.amount)} />
        <Figure label={tx('refunds')} value={formatMoney(report.refunds.amount)} />
        <Figure label={tx('deposits')} value={formatMoney(report.deposits.taken.amount)} />
        <Figure label={tx('repayments')} value={formatMoney(report.credit.repayments.amount)} />
      </dl>
    </Card>
  );
}

function LastZCard({ shift, report, tx }: { shift: Shift; report: ShiftReport | null; tx: Tx }) {
  return (
    <Card className="flex flex-col gap-2" role="group" aria-label={tx('lastZ')}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
          <Lock aria-hidden="true" className="size-5 text-fg-muted" />
          {tx('zTitle', { n: shift.z_number ?? '—' })}
        </h2>
        <span className="text-footnote text-fg-muted">{formatDate(shift.business_date)}</span>
      </div>
      <p className="text-footnote text-fg-muted">
        {shift.device?.name ?? ''} · {tx('closedLine', { by: shift.closed_by?.name ?? '—', time: shift.closed_at ? formatDateTime(shift.closed_at) : '—' })}
        {shift.force_closed ? ` · ${tx('forced')}` : ''}
      </p>
      <dl className="divide-y divide-line">
        {report && <Figure label={tx('sales')} value={formatMoney(report.sales.net)} strong />}
        <Figure label={tx('expected')} value={shift.expected_cash === null ? '—' : formatMoney(shift.expected_cash)} />
        <Figure label={tx('counted')} value={shift.counted_cash === null ? '—' : formatMoney(shift.counted_cash)} />
        <div className="flex items-center justify-between gap-3 py-1">
          <dt className="text-footnote text-fg-muted">{tx('diff')}</dt>
          <dd>
            <Difference difference={shift.difference} tx={tx} />
          </dd>
        </div>
      </dl>
    </Card>
  );
}

// "Smena xulosasi" for an owner or manager on the phone: the branch's open shifts with their X figures, and the last closed one
// (Z). Every figure is the server's (GET /pos/shifts, the X report, the shift's frozen Z summary); an owner picks the branch.
export default function ShiftSummary() {
  const tx = useStaffText(TEXT);
  const branch = useWorkBranch();
  const summary = useBranchShiftSummary(branch.branchId, { enabled: branch.ready, ...pollingQueryOptions(POLL_INTERVAL_MS.shiftSummary) });
  const forbidden = summary.isError && (summary.error as { status?: number } | null)?.status === 403;

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('title')} subtitle={tx('subtitle')} back />
      {branch.canPick && <BranchSelect branches={branch.branches} value={branch.branchId} onChange={branch.setBranchId} label={tx('branch')} />}

      {branch.isError ? (
        <InlineError onRetry={branch.refetch} />
      ) : forbidden ? (
        <InlineError title={getApiErrorMessage(summary.error) || tx('forbidden')} />
      ) : summary.isError && !summary.data ? (
        <InlineError onRetry={() => void summary.refetch()} retrying={summary.isFetching} />
      ) : !summary.data ? (
        <CardSkeletons count={2} lines={4} />
      ) : (
        <>
          <Section title={tx('open')}>
            {summary.data.open.length === 0 ? (
              <EmptyState icon={BadgeCheck} title={tx('noOpen')} text={tx('noOpenText')} />
            ) : (
              <div className="flex flex-col gap-3">
                {summary.data.open.map(({ shift, report }) => (
                  <OpenShiftCard key={shift.uuid} shift={shift} report={report} tx={tx} />
                ))}
              </div>
            )}
          </Section>
          <Section title={tx('lastZ')}>{summary.data.lastClosed ? <LastZCard shift={summary.data.lastClosed} report={summary.data.lastClosed.summary} tx={tx} /> : <p className="text-footnote text-fg-muted">{tx('noZ')}</p>}</Section>
        </>
      )}
    </div>
  );
}
