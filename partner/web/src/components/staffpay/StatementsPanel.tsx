'use client';

import { Fragment, useRef, useState } from 'react';
import { ChevronDown, ChevronUp, FileText, Plus } from 'lucide-react';
import { isStaffApiError } from '@rizqim/staff';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, formatMoney, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Pill, Td, Th, type Tone } from '../reports/ui';
import type { Statement, StatementLine, StatementStatus, StatementSummary } from './api';
import { formatMinutes, parseAmountInput, todayIso } from './describe';
import { useAdjustLine, useCreateStatement, useDeleteStatement, usePayLine, useReopenStatement, useStatement, useStatementAction, useStatements } from './hooks';

const STATUS_TONE: Record<StatementStatus, Tone> = { draft: 'warning', closed: 'info', paid: 'success' };

// "Hisob-kitob": the pay statement of each period. A draft is recomputed from the rules and the work and sales as they
// are; closing freezes it; then each line is paid from the till (a wage cash movement on an open shift) or another way.
export function StatementsPanel({ branchId, canManage }: { branchId: number; canManage: boolean }) {
  const { t, language } = useI18n();
  const list = useStatements(branchId);
  const [selected, setSelected] = useState<string | null>(null);
  const [date, setDate] = useState(() => todayIso());
  const key = useRef<string>(globalThis.crypto.randomUUID());
  const create = useCreateStatement({
    onSuccess: (statement) => {
      key.current = globalThis.crypto.randomUUID();
      setSelected(statement.uuid);
    },
  });
  const existing = create.error && isStaffApiError(create.error) && create.error.reason === 'statement_exists' ? existingUuid(create.error.body) : null;

  if (list.isPending) return <CardSkeletons count={2} lines={3} label={t('loading')} />;
  if (list.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(list.error, t).message} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />;

  return (
    <div className="flex flex-col gap-4">
      {canManage && (
        <Card className="flex flex-col gap-3">
          <h2 className="text-headline font-bold text-fg">{t('sp_statementNew')}</h2>
          <p className="text-footnote text-fg-muted">{t('sp_statementNewHint')}</p>
          <div className="flex flex-wrap items-end gap-3">
            <DateField label={t('sp_statementDate')} value={date} onChange={setDate} />
            <Button icon={Plus} loading={create.isPending} disabled={date === ''} onClick={() => create.mutate({ branchId, date, key: key.current })}>
              {t('sp_statementCreate')}
            </Button>
          </div>
          {create.error ? (
            <div className="flex flex-wrap items-center gap-3">
              <ErrorBanner message={describeError(create.error, t).message} />
              {existing && (
                <Button variant="secondary" onClick={() => setSelected(existing)}>
                  {t('sp_statementOpenExisting')}
                </Button>
              )}
            </div>
          ) : null}
        </Card>
      )}

      {list.data.length === 0 ? (
        <EmptyState icon={FileText} title={t('sp_statementsEmptyTitle')} text={t('sp_statementsEmptyText')} />
      ) : (
        <Card>
          <DataTable caption={t('sp_tabStatements')}>
            <thead>
              <tr>
                <Th>{t('sp_colPeriod')}</Th>
                <Th>{t('sp_colStatus')}</Th>
                <Th numeric>{t('sp_colPeople')}</Th>
                <Th numeric>{t('sp_colPayable')}</Th>
                <Th numeric>{t('sp_colPaid')}</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {list.data.map((statement) => (
                <tr key={statement.uuid} aria-current={statement.uuid === selected ? 'true' : undefined} className={statement.uuid === selected ? 'bg-brand-soft' : undefined}>
                  <Td className="whitespace-nowrap font-bold">{periodText(statement, language)}</Td>
                  <Td>
                    <Pill tone={STATUS_TONE[statement.status]}>{t(`sp_status_${statement.status}`)}</Pill>
                  </Td>
                  <Td numeric>{statement.lines_count}</Td>
                  <Td numeric className="font-bold">{formatMoney(statement.payable_total, language)}</Td>
                  <Td numeric>{formatMoney(statement.paid_total, language)}</Td>
                  <Td className="text-right">
                    <Button variant="secondary" onClick={() => setSelected(statement.uuid)}>
                      {t('sp_open')}
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}

      {selected !== null && <StatementDetail key={selected} uuid={selected} canManage={canManage} onGone={() => setSelected(null)} />}
    </div>
  );
}

function existingUuid(body: unknown): string | null {
  const data = (body as { data?: { statement_uuid?: unknown } } | null)?.data;
  return typeof data?.statement_uuid === 'string' ? data.statement_uuid : null;
}

const periodText = (statement: StatementSummary, language: 'uz' | 'ru' | 'en') =>
  statement.period_from === statement.period_to ? formatDay(statement.period_from, language) : `${formatDay(statement.period_from, language)} – ${formatDay(statement.period_to, language)}`;

function StatementDetail({ uuid, canManage, onGone }: { uuid: string; canManage: boolean; onGone: () => void }) {
  const { t, language } = useI18n();
  const query = useStatement(uuid);
  const act = useStatementAction();
  const [reopening, setReopening] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [adjusting, setAdjusting] = useState<StatementLine | null>(null);
  const [paying, setPaying] = useState<StatementLine | null>(null);
  const [open, setOpen] = useState<string | null>(null);
  const remove = useDeleteStatement({ onSuccess: onGone });

  if (query.isPending) return <CardSkeletons count={1} lines={4} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  const statement: Statement = query.data;
  const draft = statement.status === 'draft';

  return (
    <Card className="flex flex-col gap-4" aria-labelledby="sp-statement-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="sp-statement-title" className="text-headline font-bold text-fg">
            {t('sp_statementTitle', { period: periodText(statement, language) })}
          </h2>
          <p className="text-footnote text-fg-muted">
            {statement.period_label} · <Pill tone={STATUS_TONE[statement.status]}>{t(`sp_status_${statement.status}`)}</Pill>
          </p>
        </div>
        {canManage && (
          <div className="flex flex-wrap gap-2">
            {draft && (
              <>
                <Button variant="secondary" loading={act.isPending && act.variables?.action === 'recompute'} onClick={() => act.mutate({ uuid, action: 'recompute' })}>
                  {t('sp_recompute')}
                </Button>
                <Button loading={act.isPending && act.variables?.action === 'close'} onClick={() => act.mutate({ uuid, action: 'close' })}>
                  {t('sp_close')}
                </Button>
                <Button variant="ghost" onClick={() => setDeleting(true)}>
                  {t('sp_delete')}
                </Button>
              </>
            )}
            {statement.status === 'closed' && !statement.lines.some((line) => line.paid) && (
              <Button variant="secondary" onClick={() => setReopening(true)}>
                {t('sp_reopen')}
              </Button>
            )}
          </div>
        )}
      </div>
      {act.error ? <ErrorBanner message={describeError(act.error, t).message} /> : null}

      <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <Total label={t('sp_totalGross')} value={formatMoney(statement.gross_total, language)} />
        <Total label={t('sp_totalPayable')} value={formatMoney(statement.payable_total, language)} strong />
        <Total label={t('sp_totalPaid')} value={formatMoney(statement.paid_total, language)} />
      </dl>

      {statement.warnings.length > 0 && (
        <div role="status" className="rounded-control bg-warning-soft p-3 text-footnote text-warning-soft-fg">
          <p className="mb-1 font-bold">{t('sp_warningsTitle')}</p>
          <ul className="flex flex-col gap-1">
            {statement.warnings.map((warning) => (
              <li key={`${warning.type}-${warning.user_id}`}>{warning.type === 'no_rule' ? t('sp_warnNoRule', { name: warning.name ?? '' }) : t('sp_warnOpenSessions', { name: warning.name ?? '', n: warning.count ?? 1 })}</li>
            ))}
          </ul>
        </div>
      )}

      {statement.lines.length === 0 ? (
        <EmptyState icon={FileText} title={t('sp_linesEmptyTitle')} text={t('sp_linesEmptyText')} />
      ) : (
        <DataTable caption={t('sp_linesCaption')}>
          <thead>
            <tr>
              <Th>{t('sp_colPerson')}</Th>
              <Th numeric>{t('sp_colShifts')}</Th>
              <Th numeric>{t('sp_colHours')}</Th>
              <Th numeric>{t('sp_colSales')}</Th>
              <Th numeric>{t('sp_colGross')}</Th>
              <Th numeric>{t('sp_colAdvances')}</Th>
              <Th numeric>{t('sp_colAdjustment')}</Th>
              <Th numeric>{t('sp_colPayable')}</Th>
              <Th>{t('sp_colStatus')}</Th>
            </tr>
          </thead>
          <tbody>
            {statement.lines.map((line) => (
              <Fragment key={line.uuid}>
                <tr>
                  <Td>
                    <button type="button" className="flex items-center gap-1 text-left font-bold text-fg underline-offset-2 hover:underline" aria-expanded={open === line.uuid} onClick={() => setOpen(open === line.uuid ? null : line.uuid)}>
                      {open === line.uuid ? <ChevronUp aria-hidden="true" className="size-4" /> : <ChevronDown aria-hidden="true" className="size-4" />}
                      {line.user_name}
                    </button>
                    <span className="block text-caption text-fg-muted">{line.role_label}</span>
                  </Td>
                  <Td numeric>{line.metrics.shifts}</Td>
                  <Td numeric>{formatMinutes(line.metrics.minutes, t)}</Td>
                  <Td numeric>{formatMoney(line.metrics.sales, language)}</Td>
                  <Td numeric>{formatMoney(line.gross, language)}</Td>
                  <Td numeric>{Number(line.advances) > 0 ? `−${formatMoney(line.advances, language)}` : '—'}</Td>
                  <Td numeric>
                    {Number(line.adjustment) !== 0 ? formatMoney(line.adjustment, language) : '—'}
                    {line.adjustment_reason ? <span className="block text-caption text-fg-muted">{line.adjustment_reason}</span> : null}
                  </Td>
                  <Td numeric className="font-bold">{formatMoney(line.payable, language)}</Td>
                  <Td className="whitespace-nowrap">
                    {line.paid ? (
                      <Pill tone="success">{t(line.paid_method === 'cash_drawer' ? 'sp_paidCash' : 'sp_paidOther')}</Pill>
                    ) : canManage && draft ? (
                      <Button variant="secondary" onClick={() => setAdjusting(line)}>
                        {t('sp_adjust')}
                      </Button>
                    ) : canManage && statement.status !== 'draft' ? (
                      <Button onClick={() => setPaying(line)}>{t('sp_pay')}</Button>
                    ) : (
                      <Pill tone="neutral">{t('sp_unpaid')}</Pill>
                    )}
                  </Td>
                </tr>
                {open === line.uuid && (
                  <tr>
                    <td colSpan={9} className="border-t border-line bg-surface-2 px-3 py-2">
                      <ul className="flex flex-col gap-1 text-footnote text-fg">
                        {line.components.map((component, index) => (
                          <li key={`${component.type}-${index}`} className="flex flex-wrap justify-between gap-2">
                            <span>
                              <span className="font-bold">{component.label}</span>: {component.detail}
                            </span>
                            <span className="tabular-nums">{formatMoney(component.amount, language)}</span>
                          </li>
                        ))}
                        <li className="text-fg-muted">
                          {t('sp_lineMetrics', {
                            bills: line.metrics.bills,
                            service: formatMoney(line.metrics.service_charge, language),
                            events: line.metrics.events,
                            tips: formatMoney(line.metrics.tips, language),
                          })}
                        </li>
                      </ul>
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
          </tbody>
        </DataTable>
      )}

      {adjusting !== null && <AdjustDialog statement={statement} line={adjusting} onClose={() => setAdjusting(null)} />}
      {paying !== null && <PayDialog statement={statement} line={paying} onClose={() => setPaying(null)} />}
      {reopening && <ReopenDialog uuid={uuid} onClose={() => setReopening(false)} />}
      {deleting && (
        <ConfirmDialog
          title={t('sp_statementDeleteTitle')}
          text={t('sp_statementDeleteText')}
          confirmLabel={t('sp_delete')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(uuid)}
          onClose={() => setDeleting(false)}
        />
      )}
    </Card>
  );
}

function Total({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="rounded-control bg-surface-2 p-3">
      <dt className="text-caption text-fg-muted">{label}</dt>
      <dd className={strong ? 'text-headline font-bold text-fg' : 'text-body font-bold text-fg'}>{value}</dd>
    </div>
  );
}

function AdjustDialog({ statement, line, onClose }: { statement: Statement; line: StatementLine; onClose: () => void }) {
  const { t } = useI18n();
  const adjust = useAdjustLine({ onSuccess: onClose });
  const [amount, setAmount] = useState(Number(line.adjustment) !== 0 ? String(Number(line.adjustment)) : '');
  const [reason, setReason] = useState(line.adjustment_reason ?? '');
  const [problem, setProblem] = useState<string | null>(null);
  const failure = adjust.error ? describeError(adjust.error, t) : null;

  const submit = () => {
    const value = amount.trim() === '' ? '0' : parseAmountInput(amount);
    if (value === null) return setProblem(t('sp_errAmount'));
    setProblem(null);
    adjust.mutate({ uuid: statement.uuid, line: line.uuid, adjustment: value, reason: reason.trim() === '' ? null : reason.trim() });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('sp_adjustTitle', { name: line.user_name ?? '' })}
      description={t('sp_adjustHint')}
      busy={adjust.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={adjust.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={adjust.isPending} onClick={submit}>
            {t('sp_save')}
          </Button>
        </>
      }
    >
      <Field label={t('sp_adjustAmount')} error={problem ?? failure?.fields.adjustment}>
        {(control) => <input {...control} data-autofocus className={fieldClass} inputMode="decimal" value={amount} onChange={(event) => setAmount(event.target.value)} />}
      </Field>
      <Field label={t('sp_reason')} error={failure?.fields.reason}>
        {(control) => <input {...control} className={fieldClass} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

function PayDialog({ statement, line, onClose }: { statement: Statement; line: StatementLine; onClose: () => void }) {
  const { t, language } = useI18n();
  const key = useRef<string>(globalThis.crypto.randomUUID());
  const pay = usePayLine({ onSuccess: onClose });
  const [method, setMethod] = useState<'cash_drawer' | 'other'>(statement.open_shifts.length > 0 && Number(line.payable) > 0 ? 'cash_drawer' : 'other');
  const [shiftUuid, setShiftUuid] = useState(statement.open_shifts[0]?.uuid ?? '');
  const [note, setNote] = useState('');
  const failure = pay.error ? describeError(pay.error, t) : null;

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('sp_payTitle', { name: line.user_name ?? '' })}
      description={t('sp_payAmount', { amount: formatMoney(line.payable, language) })}
      busy={pay.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={pay.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button
            loading={pay.isPending}
            disabled={method === 'cash_drawer' && shiftUuid === ''}
            onClick={() => pay.mutate({ uuid: statement.uuid, line: line.uuid, method, shiftUuid: method === 'cash_drawer' ? shiftUuid : null, note: note.trim() === '' ? null : note.trim(), key: key.current })}
          >
            {t('sp_payConfirm')}
          </Button>
        </>
      }
    >
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('sp_payMethod')}</legend>
        <label className="flex min-h-11 cursor-pointer items-center gap-2 text-body text-fg">
          <input type="radio" name="sp-pay-method" className="size-5 accent-[var(--color-brand)]" checked={method === 'cash_drawer'} disabled={statement.open_shifts.length === 0} onChange={() => setMethod('cash_drawer')} />
          {t('sp_payCash')}
        </label>
        <label className="flex min-h-11 cursor-pointer items-center gap-2 text-body text-fg">
          <input type="radio" name="sp-pay-method" className="size-5 accent-[var(--color-brand)]" checked={method === 'other'} onChange={() => setMethod('other')} />
          {t('sp_payOther')}
        </label>
        {statement.open_shifts.length === 0 && <p className="text-caption text-fg-muted">{t('sp_payNoShift')}</p>}
      </fieldset>
      {method === 'cash_drawer' && (
        <Field label={t('sp_payShift')} hint={t('sp_payShiftHint')}>
          {(control) => (
            <select {...control} className={fieldClass} value={shiftUuid} onChange={(event) => setShiftUuid(event.target.value)}>
              {statement.open_shifts.map((shift) => (
                <option key={shift.uuid} value={shift.uuid}>
                  {t('sp_payShiftOption', { name: shift.device_name ?? t('sp_till'), cash: formatMoney(shift.expected_cash, language) })}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
      <Field label={t('sp_note')}>{(control) => <input {...control} className={fieldClass} maxLength={500} value={note} onChange={(event) => setNote(event.target.value)} />}</Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

function ReopenDialog({ uuid, onClose }: { uuid: string; onClose: () => void }) {
  const { t } = useI18n();
  const reopen = useReopenStatement({ onSuccess: onClose });
  const [reason, setReason] = useState('');
  const failure = reopen.error ? describeError(reopen.error, t) : null;

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('sp_reopenTitle')}
      description={t('sp_reopenHint')}
      busy={reopen.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={reopen.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={reopen.isPending} disabled={reason.trim() === ''} onClick={() => reopen.mutate({ uuid, reason: reason.trim() })}>
            {t('sp_reopen')}
          </Button>
        </>
      }
    >
      <Field label={t('sp_reason')} error={failure?.fields.reason}>
        {(control) => <input {...control} data-autofocus className={fieldClass} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
