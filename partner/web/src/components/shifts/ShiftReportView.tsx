'use client';

import type { ReactNode } from 'react';
import { Card, cx, formatDateTime, formatMoney, Pill } from '@rizqim/ui';
import type { Shift, ShiftReport } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { labelFor } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';

type Language = 'uz' | 'ru' | 'en';

function Figure({ label, value, strong, tone }: { label: string; value: ReactNode; strong?: boolean; tone?: 'danger' | 'success' | 'warning' }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-b border-line py-1.5 last:border-b-0 print:border-fg-subtle">
      <dt className="text-body text-fg-muted print:text-fg">{label}</dt>
      <dd className={cx('text-right tabular-nums text-body', strong ? 'text-headline font-extrabold text-fg' : 'font-bold text-fg', tone === 'danger' && 'text-danger-soft-fg', tone === 'success' && 'text-success-soft-fg', tone === 'warning' && 'text-warning-soft-fg')}>{value}</dd>
    </div>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <Card className="flex flex-col gap-2 break-inside-avoid print:border-fg-subtle print:shadow-none" aria-label={title}>
      <h2 className="text-headline font-bold text-fg">{title}</h2>
      {children}
    </Card>
  );
}

// The difference between the counted and the expected cash, in words: the colour only backs the sign up
export function DifferenceFigure({ difference, language }: { difference: string | null; language: Language }) {
  const { t } = useI18n();
  if (difference === null) return <span className="text-fg-muted">{t('shift_notCounted')}</span>;
  const value = Number(difference);
  if (value === 0) return <Pill tone="success">{t('shift_diffZero')}</Pill>;
  return value < 0 ? <Pill tone="danger">{t('shift_diffShort', { amount: formatMoney(Math.abs(value), language) })}</Pill> : <Pill tone="warning">{t('shift_diffOver', { amount: formatMoney(value, language) })}</Pill>;
}

// One shift's report as the server built it: the X report of an open shift or the Z report of a closed one, with the
// cash count. Printable (the browser's print dialog: the menu and the buttons are left out).
export function ShiftReportView({ shift, report, branchName }: { shift: Shift; report: ShiftReport; branchName: string | null }) {
  const { t, language } = useI18n();
  const money = (value: string | number) => formatMoney(value, language);
  const closed = shift.status === 'closed';
  const title = closed ? t('shift_zTitle', { n: shift.z_number ?? '—' }) : t('shift_xTitle');
  const denominations = Object.entries(shift.counted_breakdown ?? {}).sort(([a], [b]) => Number(b) - Number(a));

  return (
    <article className="flex flex-col gap-4" aria-label={title}>
      <header className="flex flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-title font-bold text-fg">{title}</h2>
          <Pill tone={closed ? 'neutral' : 'success'}>{closed ? t('shift_closed') : t('shift_open')}</Pill>
          {shift.force_closed && <Pill tone="warning">{t('shift_forced')}</Pill>}
        </div>
        <p className="text-footnote text-fg-muted">
          {[branchName, shift.device?.name, t('shift_businessDay', { date: shift.business_date })].filter(Boolean).join(' · ')}
        </p>
        <p className="text-footnote text-fg-muted">
          {t('shift_openedLine', { time: formatDateTime(shift.opened_at, language), by: shift.opened_by?.name ?? '—' })}
          {shift.closed_at ? ` · ${t('shift_closedLine', { time: formatDateTime(shift.closed_at, language), by: shift.closed_by?.name ?? '—' })}` : ''}
        </p>
        {shift.close_note && <p className="text-footnote text-fg-muted">{t('shift_closeNote', { note: shift.close_note })}</p>}
        {!closed && <p className="text-footnote text-fg-muted">{t('shift_xNote', { time: formatDateTime(report.generated_at, language) })}</p>}
      </header>

      <div className="grid gap-4 lg:grid-cols-2 print:grid-cols-2">
        <Section title={t('shift_sales')}>
          <dl>
            <Figure label={t('shift_bills')} value={report.sales.bills} />
            <Figure label={t('shift_billsTotal')} value={money(report.sales.bills_total)} />
            <Figure label={t('shift_subtotal')} value={money(report.sales.subtotal)} />
            <Figure label={t('shift_discount')} value={money(report.sales.discount)} />
            <Figure label={t('shift_serviceCharge')} value={money(report.sales.service_charge)} />
            <Figure label={t('shift_roomFee')} value={money(report.sales.room_fee)} />
            <Figure label={t('shift_minSpendTopup')} value={money(report.sales.min_spend_topup)} />
            <Figure label={t('shift_placeFee')} value={money(report.sales.place_fee)} />
            <Figure label={t('shift_average')} value={money(report.sales.average_bill)} />
            <Figure label={t('shift_received')} value={money(report.sales.received)} />
            <Figure label={t('shift_refunded')} value={money(report.sales.refunded)} />
            <Figure label={t('shift_net')} value={money(report.sales.net)} strong />
          </dl>
        </Section>

        <Section title={t('shift_cash')}>
          <dl>
            <Figure label={t('shift_cashOpening')} value={money(report.cash.opening)} />
            <Figure label={t('shift_cashSales')} value={`+ ${money(report.cash.sales)}`} />
            <Figure label={t('shift_cashIn')} value={`+ ${money(report.cash.cash_in)}`} />
            <Figure label={t('shift_cashDeposits')} value={`+ ${money(report.cash.deposits)}`} />
            <Figure label={t('shift_cashRepayments')} value={`+ ${money(report.cash.repayments)}`} />
            <Figure label={t('shift_cashRefunds')} value={`− ${money(report.cash.refunds)}`} />
            <Figure label={t('shift_cashDepositRefunds')} value={`− ${money(report.cash.deposit_refunds)}`} />
            <Figure label={t('shift_cashOut')} value={`− ${money(report.cash.cash_out)}`} />
            <Figure label={t('shift_cashWages')} value={`− ${money(report.cash.wages)}`} />
            <Figure label={t('shift_cashPurchases')} value={`− ${money(report.cash.purchases)}`} />
            <Figure label={t('shift_cashExpected')} value={money(report.cash.expected)} strong />
            {closed && (
              <>
                <Figure label={t('shift_cashCounted')} value={shift.counted_cash === null ? '—' : money(shift.counted_cash)} strong />
                <Figure label={t('shift_cashDifference')} value={<DifferenceFigure difference={shift.difference} language={language} />} />
              </>
            )}
          </dl>
          {closed && denominations.length > 0 && (
            <p className="text-footnote text-fg-muted">
              {t('shift_banknotes')}: {denominations.map(([note, count]) => `${new Intl.NumberFormat('en').format(Number(note)).replace(/,/g, ' ')} × ${count}`).join(', ')}
            </p>
          )}
        </Section>
      </div>

      <div className="grid gap-4 lg:grid-cols-2 print:grid-cols-2">
        <Section title={t('shift_tenders')}>
          <DataTable caption={t('shift_tenders')}>
            <thead>
              <tr>
                <Th>{t('shift_tender')}</Th>
                <Th numeric>{t('shift_count')}</Th>
                <Th numeric>{t('shift_amount')}</Th>
              </tr>
            </thead>
            <tbody>
              {report.by_tender.map((row) => (
                <tr key={row.tender}>
                  <Td>{labelFor(t, 'shift_tender_', row.tender)}</Td>
                  <Td numeric>{row.count}</Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {money(row.amount)}
                  </Td>
                </tr>
              ))}
              {report.cards.map((row) => (
                <tr key={`card-${row.card_type}`}>
                  <Td muted>↳ {t('shift_cardType', { type: row.card_type })}</Td>
                  <Td numeric muted>
                    {row.count}
                  </Td>
                  <Td numeric muted className="whitespace-nowrap">
                    {money(row.amount)}
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Section>

        <Section title={t('shift_channels')}>
          {report.by_channel.length === 0 ? (
            <p className="text-footnote text-fg-muted">{t('shift_none')}</p>
          ) : (
            <DataTable caption={t('shift_channels')}>
              <thead>
                <tr>
                  <Th>{t('shift_channel')}</Th>
                  <Th numeric>{t('shift_orders')}</Th>
                  <Th numeric>{t('shift_amount')}</Th>
                </tr>
              </thead>
              <tbody>
                {report.by_channel.map((row) => (
                  <tr key={row.channel}>
                    <Td>{labelFor(t, 'shift_channel_', row.channel)}</Td>
                    <Td numeric>{row.orders}</Td>
                    <Td numeric className="whitespace-nowrap font-bold">
                      {money(row.amount)}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
          )}
        </Section>
      </div>

      <Section title={t('shift_waiters')}>
        {report.by_waiter.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('shift_none')}</p>
        ) : (
          <DataTable caption={t('shift_waiters')}>
            <thead>
              <tr>
                <Th>{t('shift_waiter')}</Th>
                <Th numeric>{t('shift_orders')}</Th>
                <Th numeric>{t('shift_bills')}</Th>
                <Th numeric>{t('shift_amount')}</Th>
                <Th numeric>{t('shift_serviceCharge')}</Th>
              </tr>
            </thead>
            <tbody>
              {report.by_waiter.map((row) => (
                <tr key={row.waiter_id ?? 'none'}>
                  <Td>{row.name ?? t('shift_noWaiter')}</Td>
                  <Td numeric>{row.orders}</Td>
                  <Td numeric>{row.bills}</Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {money(row.amount)}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {money(row.service_charge)}
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </Section>

      <div className="grid gap-4 lg:grid-cols-3 print:grid-cols-3">
        <Section title={t('shift_deposits')}>
          <dl>
            <Figure label={t('shift_depTaken')} value={`${money(report.deposits.taken.amount)} (${report.deposits.taken.count})`} />
            <Figure label={t('shift_depTakenCash')} value={money(report.deposits.taken.cash)} />
            <Figure label={t('shift_depApplied')} value={`${money(report.deposits.applied.amount)} (${report.deposits.applied.count})`} />
            <Figure label={t('shift_depRefunded')} value={`${money(report.deposits.refunded.amount)} (${report.deposits.refunded.count})`} />
          </dl>
        </Section>
        <Section title={t('shift_credit')}>
          <dl>
            <Figure label={t('shift_creditSales')} value={`${money(report.credit.sales.amount)} (${report.credit.sales.count})`} />
            <Figure label={t('shift_creditRepayments')} value={`${money(report.credit.repayments.amount)} (${report.credit.repayments.count})`} />
            <Figure label={t('shift_creditRepaymentsCash')} value={money(report.credit.repayments.cash)} />
          </dl>
        </Section>
        <Section title={t('shift_other')}>
          <dl>
            <Figure label={t('shift_discounts')} value={`${money(report.discounts.amount)} (${report.discounts.count})`} />
            <Figure label={t('shift_voids')} value={`${money(report.voids.amount)} (${report.voids.count})`} />
            <Figure label={t('shift_refundsCount')} value={`${money(report.refunds.amount)} (${report.refunds.count})`} />
          </dl>
        </Section>
      </div>

      <Section title={t('shift_movements')}>
        {report.movements.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('shift_none')}</p>
        ) : (
          <DataTable caption={t('shift_movements')}>
            <thead>
              <tr>
                <Th>{t('fin_colDate')}</Th>
                <Th>{t('shift_moveType')}</Th>
                <Th numeric>{t('shift_amount')}</Th>
                <Th>{t('shift_moveWho')}</Th>
                <Th>{t('fin_colNotes')}</Th>
              </tr>
            </thead>
            <tbody>
              {report.movements.map((movement) => (
                <tr key={movement.uuid}>
                  <Td muted className="whitespace-nowrap">
                    {movement.created_at ? formatDateTime(movement.created_at, language) : '—'}
                  </Td>
                  <Td>
                    {labelFor(t, 'shift_move_', movement.type)}
                    {movement.category ? <span className="block text-caption text-fg-muted">{movement.category}</span> : null}
                  </Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {money(movement.amount)}
                  </Td>
                  <Td>{[movement.employee?.name, movement.user?.name].filter(Boolean).join(' / ') || '—'}</Td>
                  <Td muted className="max-w-56 break-words">
                    {movement.note ?? '—'}
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </Section>
    </article>
  );
}
