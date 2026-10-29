'use client';

import { useState, type FormEvent } from 'react';
import { Inbox, Info, Save } from 'lucide-react';
import { Button, Card, EmptyState, Field, fieldClass, FormError, InlineError, Notice, Skeleton, formatDate, formatDateTime, formatMoney } from '@rizqim/ui';
import {
  CARD_MODES,
  TERMINAL_CHECKLIST,
  parseBankRows,
  useBankReports,
  useEnterBankReport,
  useSaveSmartKassa,
  useSaveTerminalSetup,
  useShifts,
  useTerminalFlow,
  type BankTerminalReport,
  type CardMode,
  type TerminalChecklistKey,
  type TerminalFlowBranch,
  type TerminalFlowDevice,
  type TerminalSetupPatch,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { useBranchScope } from '../shell/BranchScope';
import { Gate } from '../pos/Gate';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import { Check } from './formBits';

const AMOUNT = /^-?\d+(\.\d{1,2})?$/;
const clean = (value: string) => value.replace(/\s/g, '').replace(',', '.');

// The card flow of the fiscal page (P6-16, D5): how each branch takes cards and its installation checklist, which tills are
// smart kassas (settings.manage), and the bank terminal's day totals compared with the Z (finance.view or reports.view to
// read, finance.manage or pos.close_shift to enter one). The server compares and alerts; the screen only shows its answer.
export function TerminalFlowPanel({ canSetup, canReadReports, canEnter }: { canSetup: boolean; canReadReports: boolean; canEnter: boolean }) {
  const { t } = useI18n();
  return (
    <div className="flex max-w-5xl flex-col gap-5">
      <p role="note" className="flex items-start gap-2 rounded-card bg-info-soft px-4 py-3 text-footnote text-info-soft-fg">
        <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        {t('fterm_lawNote')}
      </p>
      {canSetup && <SetupSection />}
      {canReadReports && <ReportsSection canEnter={canEnter} />}
    </div>
  );
}

function SetupSection() {
  const { branchId } = useBranchScope();
  const flow = useTerminalFlow(branchId ?? undefined);
  const { t } = useI18n();
  return (
    <Gate query={flow} count={2}>
      {(overview) => (
        <div className="flex flex-col gap-4">
          <p className="text-footnote text-fg-muted">
            {overview.fiscal.enabled ? t('fterm_fiscalOn', { driver: overview.fiscal.smart_kassa_driver }) : t('fterm_fiscalOff')}
          </p>
          {overview.branches.map((branch) => (
            <BranchCard key={branch.branch.id} branch={branch} />
          ))}
        </div>
      )}
    </Gate>
  );
}

interface SetupDraft {
  mode: CardMode | '';
  checklist: Record<TerminalChecklistKey, boolean>;
  threshold: string;
  ask: boolean;
  shared: boolean;
}

function draftOf(branch: TerminalFlowBranch): SetupDraft {
  const done = Object.fromEntries(TERMINAL_CHECKLIST.map((key) => [key, branch.setup.checklist.find((item) => item.key === key)?.done ?? false])) as Record<TerminalChecklistKey, boolean>;
  return { mode: (branch.setup.card_mode ?? '') as CardMode | '', checklist: done, threshold: branch.setup.difference_threshold, ask: branch.setup.ask_bank_total, shared: branch.setup.shared_terminal ?? false };
}

function BranchCard({ branch }: { branch: TerminalFlowBranch }) {
  const { t, language } = useI18n();
  const [initial, setInitial] = useState(() => draftOf(branch));
  const [draft, setDraft] = useState(initial);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<string | null>(null);
  const [saved, setSaved] = useState<string | null>(null);
  // The server's answer to the last save (who ticked what, whether it is complete) until the list is read again
  const [answer, setAnswer] = useState<TerminalFlowBranch['setup'] | null>(null);
  const view = answer ?? branch.setup;
  const save = useSaveTerminalSetup();
  const heading = `fterm-branch-${branch.branch.id}`;

  if (!branch.module_enabled) {
    return (
      <Card className="flex flex-col gap-2">
        <h2 className="text-headline font-bold text-fg">{branch.branch.name}</h2>
        <p className="text-footnote text-fg-muted">{t('fterm_moduleOff')}</p>
      </Card>
    );
  }

  const patch = (changes: Partial<SetupDraft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    setErrors(NO_ERRORS);
    const threshold = clean(draft.threshold);
    if (!/^\d+(\.\d{1,2})?$/.test(threshold)) {
      setLocal(t('fterm_errThreshold'));
      return;
    }
    setLocal(null);
    const body: TerminalSetupPatch = {};
    if (draft.mode !== initial.mode) body.card_mode = draft.mode === '' ? null : draft.mode;
    const changed = TERMINAL_CHECKLIST.filter((key) => draft.checklist[key] !== initial.checklist[key]);
    if (changed.length > 0) body.checklist = Object.fromEntries(changed.map((key) => [key, draft.checklist[key]]));
    if (threshold !== clean(initial.threshold)) body.difference_threshold = threshold;
    if (draft.ask !== initial.ask) body.ask_bank_total = draft.ask;
    if (draft.shared !== initial.shared) body.shared_terminal = draft.shared;
    save.mutate(
      { branchId: branch.branch.id, patch: body },
      {
        onSuccess: (setup) => {
          const next = draftOf({ ...branch, setup });
          setInitial(next);
          setDraft(next);
          setAnswer(setup);
          setSaved(t('fterm_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, ['card_mode', 'difference_threshold'], t)),
      },
    );
  };

  return (
    <Card className="flex flex-col gap-4">
      <form onSubmit={submit} noValidate aria-labelledby={heading} className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <h2 id={heading} className="text-headline font-bold text-fg">
            {branch.branch.name}
          </h2>
          {view.checklist_complete ? <Pill tone="success">{t('fterm_ready')}</Pill> : <Pill tone="warning">{t('fterm_notReady')}</Pill>}
        </div>
        <Notice message={saved} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('fterm_mode')} hint={draft.mode ? t(`fterm_modeHint_${draft.mode}`) : t('fterm_modeHint_none_chosen')} error={errors.fields.card_mode}>
            {(props) => (
              <select {...props} value={draft.mode} onChange={(event) => patch({ mode: event.target.value as CardMode | '' })} className={fieldClass}>
                <option value="">{t('fterm_modeNone')}</option>
                {CARD_MODES.map((mode) => (
                  <option key={mode} value={mode}>
                    {t(`fterm_mode_${mode}`)}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field label={t('fterm_threshold')} hint={t('fterm_thresholdHint')} error={local ?? errors.fields.difference_threshold}>
            {(props) => <input {...props} inputMode="decimal" value={draft.threshold} onChange={(event) => patch({ threshold: event.target.value })} className={fieldClass} />}
          </Field>
        </div>
        <Check checked={draft.ask} onChange={(ask) => patch({ ask })} label={t('fterm_ask')} hint={t('fterm_askHint')} />
        <Check checked={draft.shared} onChange={(shared) => patch({ shared })} label={t('fterm_shared')} hint={t('fterm_sharedHint')} />
        <fieldset className="flex flex-col gap-1 rounded-card border border-line p-3">
          <legend className="px-1 text-body font-bold text-fg">{t('fterm_checklist')}</legend>
          {TERMINAL_CHECKLIST.map((key) => {
            const item = view.checklist.find((row) => row.key === key);
            const stamp = item?.done && item.done_at ? t('fterm_doneBy', { name: item.done_by?.name ?? '—', date: formatDateTime(item.done_at, language) }) : null;
            return (
              <Check
                key={key}
                checked={draft.checklist[key]}
                onChange={(done) => patch({ checklist: { ...draft.checklist, [key]: done } })}
                label={t(`fterm_item_${key}`)}
                hint={stamp ? `${t(`fterm_itemHint_${key}`)} ${stamp}` : t(`fterm_itemHint_${key}`)}
              />
            );
          })}
        </fieldset>
        <FormError message={errors.form} />
        <div>
          <Button type="submit" icon={Save} loading={save.isPending}>
            {t('fterm_save')}
          </Button>
        </div>
      </form>

      <section aria-label={t('fterm_tills')} className="flex flex-col gap-2">
        <h3 className="text-body font-bold text-fg">{t('fterm_tills')}</h3>
        {branch.devices.length === 0 ? <p className="text-footnote text-fg-muted">{t('fterm_noTills')}</p> : branch.devices.map((device) => <TillRow key={device.uuid} device={device} />)}
      </section>
    </Card>
  );
}

function TillRow({ device }: { device: TerminalFlowDevice }) {
  const { t } = useI18n();
  const [smart, setSmart] = useState(device.smart_kassa);
  const [kassaId, setKassaId] = useState(device.fiscal_kassa_id ?? '');
  const [answer, setAnswer] = useState<TerminalFlowDevice | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const save = useSaveSmartKassa();
  const name = device.name ?? t('fterm_tillNoName');
  const status = answer?.kassa_status ?? device.kassa_status;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setFailure(null);
    save.mutate(
      { uuid: device.uuid, patch: { smart_kassa: smart, fiscal_kassa_id: kassaId.trim() === '' ? null : kassaId.trim() } },
      { onSuccess: setAnswer, onError: (error) => setFailure(errorText(error, t)) },
    );
  };

  return (
    <form onSubmit={submit} noValidate aria-label={t('fterm_tillAria', { name })} className="flex flex-col gap-2 rounded-control border border-line p-3 sm:flex-row sm:items-end sm:gap-4">
      <div className="min-w-0 flex-1">
        <p className="text-body font-bold text-fg">{name}</p>
        <Check checked={smart} onChange={setSmart} label={t('fterm_smart')} hint={t('fterm_smartHint')} />
      </div>
      <Field label={t('fterm_kassaId')}>
        {(props) => <input {...props} value={kassaId} maxLength={64} disabled={!smart} onChange={(event) => setKassaId(event.target.value)} className={`${fieldClass} sm:w-56`} />}
      </Field>
      <Button type="submit" variant="secondary" loading={save.isPending}>
        {t('fterm_save')}
      </Button>
      {status && <Pill tone={status.registered ? 'success' : 'warning'}>{status.registered ? t('fterm_kassaRegistered') : t('fterm_kassaNotRegistered')}</Pill>}
      {failure && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {failure}
        </p>
      )}
    </form>
  );
}

function ReportsSection({ canEnter }: { canEnter: boolean }) {
  const { t, language } = useI18n();
  const { branchId } = useBranchScope();
  const [onlyOver, setOnlyOver] = useState(false);
  const reports = useBankReports({ ...(branchId ? { branch_id: branchId } : {}), ...(onlyOver ? { over_threshold: true } : {}) });
  const [editing, setEditing] = useState<string | null>(null);
  // The answer to an entry lives here: the form starts again (its key) when a row is picked for correction or saved, which
  // would drop the message the moment it appears
  const [result, setResult] = useState<BankTerminalReport | null>(null);

  return (
    <Card className="flex flex-col gap-3">
      <div>
        <h2 className="text-headline font-bold text-fg">{t('fterm_reports')}</h2>
        <p className="text-footnote text-fg-muted">{t('fterm_reportsIntro')}</p>
      </div>
      <Check checked={onlyOver} onChange={setOnlyOver} label={t('fterm_onlyOver')} />
      {canEnter && <EntryForm key={editing ?? 'new'} shiftUuid={editing} result={result} onResult={setResult} onDone={() => setEditing(null)} />}
      {reports.isError && !reports.data ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(reports.error, t)} retryLabel={t('retry')} retrying={reports.isFetching} onRetry={() => void reports.refetch()} />
      ) : !reports.data ? (
        <Skeleton className="h-24 w-full" />
      ) : reports.data.length === 0 ? (
        <EmptyState icon={Inbox} title={t('fterm_reportsEmpty')} text={t('fterm_reportsEmptyText')} />
      ) : (
        <DataTable caption={t('fterm_reports')}>
          <thead>
            <tr>
              <Th>{t('fterm_colDay')}</Th>
              <Th>{t('fterm_colZ')}</Th>
              <Th numeric>{t('fterm_colBank')}</Th>
              <Th numeric>{t('fterm_colRizqim')}</Th>
              <Th numeric>{t('fterm_colDifference')}</Th>
              <Th>{t('fterm_colSource')}</Th>
              {canEnter && <Th />}
            </tr>
          </thead>
          <tbody>
            {reports.data.map((report) => (
              <ReportRow key={report.uuid} report={report} canEnter={canEnter} language={language} onEdit={() => { setResult(null); setEditing(report.shift_uuid); }} />
            ))}
          </tbody>
        </DataTable>
      )}
    </Card>
  );
}

function ReportRow({ report, canEnter, language, onEdit }: { report: BankTerminalReport; canEnter: boolean; language: Parameters<typeof formatMoney>[1]; onEdit: () => void }) {
  const { t } = useI18n();
  const unmatched = (report.unmatched?.bank.length ?? 0) + (report.unmatched?.rizqim.length ?? 0);
  const shifts = report.shifts ?? [];
  const corrections = report.history?.length ?? 0;
  return (
    <tr>
      <Td className="whitespace-nowrap">{formatDate(report.business_date, language)}</Td>
      <Td>
        {report.shared ? (
          <>
            {t('fterm_sharedShifts', { count: shifts.length })}
            <span className="block text-footnote text-fg-muted">{shifts.map((one) => (one.z_number !== null ? `Z #${one.z_number}` : t('fterm_open'))).join(', ')}</span>
          </>
        ) : report.z_number !== null ? (
          `Z #${report.z_number}`
        ) : (
          t('fterm_open')
        )}
      </Td>
      <Td numeric className="whitespace-nowrap">
        {formatMoney(report.bank_total, language)}
      </Td>
      <Td numeric className="whitespace-nowrap">
        {report.rizqim_total !== null ? formatMoney(report.rizqim_total, language) : '—'}
      </Td>
      <Td numeric className="whitespace-nowrap font-bold">
        <span className="flex flex-wrap items-center justify-end gap-1.5">
          <DifferenceValue report={report} language={language} />
        </span>
        {report.final === false && <span className="block text-footnote font-normal text-fg-muted">{t('fterm_pendingHint')}</span>}
        {unmatched > 0 && <span className="block text-footnote font-normal text-fg-muted">{t('fterm_unmatched', { count: unmatched })}</span>}
        {corrections > 0 && <span className="block text-footnote font-normal text-fg-muted">{t('fterm_corrected', { count: corrections })}</span>}
      </Td>
      <Td muted>{t(`fterm_source_${report.source}`)}</Td>
      {canEnter && (
        <Td>
          <Button variant="secondary" aria-label={t('fterm_editAria', { day: formatDate(report.business_date, language) })} onClick={onEdit} disabled={report.shift_uuid === null}>
            {t('fterm_edit')}
          </Button>
        </Td>
      )}
    </tr>
  );
}

// The difference with its verdict: pending while a covered shift is open (and hidden without finance.manage), the alert
// once told (it stays after a correction), a match otherwise
function DifferenceValue({ report, language }: { report: BankTerminalReport; language: Parameters<typeof formatMoney>[1] }) {
  const { t } = useI18n();
  return (
    <>
      {report.difference !== null ? formatMoney(report.difference, language) : '—'}
      {report.final === false ? (
        <Pill tone="warning">{t('fterm_pending')}</Pill>
      ) : report.over_threshold ? (
        <Pill tone="danger">{t('fterm_mismatch')}</Pill>
      ) : (
        <Pill tone="success">{t('fterm_match')}</Pill>
      )}
    </>
  );
}

// Enter (or correct) a closed shift's bank terminal day total: the slip's total, or its rows pasted one per line ("RRN;summa")
function EntryForm({ shiftUuid, result, onResult, onDone }: { shiftUuid: string | null; result: BankTerminalReport | null; onResult: (report: BankTerminalReport | null) => void; onDone: () => void }) {
  const { t, language } = useI18n();
  const { branchId } = useBranchScope();
  const shifts = useShifts({ ...(branchId ? { branch_id: branchId } : {}), status: 'closed', limit: 30 });
  const [shift, setShift] = useState(shiftUuid ?? '');
  const [mode, setMode] = useState<'manual' | 'import'>('manual');
  const [total, setTotal] = useState('');
  const [rows, setRows] = useState('');
  const [local, setLocal] = useState<string | null>(null);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const enter = useEnterBankReport();

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onResult(null);
    setErrors(NO_ERRORS);
    if (shift === '') {
      setLocal(t('fterm_errShift'));
      return;
    }
    let input;
    if (mode === 'manual') {
      const amount = clean(total);
      if (!AMOUNT.test(amount)) {
        setLocal(t('fterm_errTotal'));
        return;
      }
      input = { source: 'manual' as const, bank_total: amount };
    } else {
      const parsed = parseBankRows(rows);
      if (parsed.bad.length > 0 || parsed.rows.length === 0) {
        setLocal(parsed.rows.length === 0 && parsed.bad.length === 0 ? t('fterm_errRowsEmpty') : t('fterm_errRows', { lines: parsed.bad.join(', ') }));
        return;
      }
      input = { source: 'import' as const, transactions: parsed.rows };
    }
    setLocal(null);
    enter.mutate(
      { shiftUuid: shift, input },
      {
        onSuccess: (report) => {
          onResult(report);
          onDone();
        },
        onError: (error) => setErrors(sortServerErrors(error, ['bank_total', 'transactions'], t)),
      },
    );
  };

  return (
    <form onSubmit={submit} noValidate aria-label={t('fterm_entry')} className="flex flex-col gap-3 rounded-card border border-line p-3">
      <h3 className="text-body font-bold text-fg">{t('fterm_entry')}</h3>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label={t('fterm_shift')}>
          {(props) => (
            <select {...props} value={shift} onChange={(event) => setShift(event.target.value)} className={fieldClass}>
              <option value="">{t('fterm_shiftPick')}</option>
              {(shifts.data ?? []).map((row) => (
                <option key={row.uuid} value={row.uuid}>
                  {`Z #${row.z_number ?? '—'} · ${formatDate(row.business_date, language)}${row.device ? ` · ${row.device.name}` : ''}`}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('fterm_entryMode')}>
          {(props) => (
            <select {...props} value={mode} onChange={(event) => setMode(event.target.value as 'manual' | 'import')} className={fieldClass}>
              <option value="manual">{t('fterm_source_manual')}</option>
              <option value="import">{t('fterm_source_import')}</option>
            </select>
          )}
        </Field>
      </div>
      {mode === 'manual' ? (
        <Field label={t('fterm_bankTotal')} error={errors.fields.bank_total}>
          {(props) => <input {...props} inputMode="decimal" value={total} onChange={(event) => setTotal(event.target.value)} className={`${fieldClass} sm:max-w-xs`} />}
        </Field>
      ) : (
        <Field label={t('fterm_rows')} hint={t('fterm_rowsHint')} error={errors.fields.transactions}>
          {(props) => <textarea {...props} rows={5} value={rows} onChange={(event) => setRows(event.target.value)} className={`${fieldClass} font-mono`} />}
        </Field>
      )}
      {local && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {local}
        </p>
      )}
      <FormError message={errors.form} />
      {result && (
        <Notice
          message={
            result.difference === null || result.final === false
              ? t('fterm_resultPending')
              : result.over_threshold
                ? t('fterm_resultMismatch', { difference: formatMoney(result.difference, language) })
                : t('fterm_resultMatch', { difference: formatMoney(result.difference, language) })
          }
        />
      )}
      <div>
        <Button type="submit" icon={Save} loading={enter.isPending}>
          {t('fterm_entrySave')}
        </Button>
      </div>
    </form>
  );
}

// The Z's bank terminal row (P6-16): the shift's bank terminal day total against its card money, under the Z report
export function BankTerminalSummary({ report }: { report: BankTerminalReport }) {
  const { t, language } = useI18n();
  return (
    <Card className="mt-4 flex flex-col gap-2">
      <h2 className="text-headline font-bold text-fg">{t('fterm_reports')}</h2>
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-body">
        <dt className="text-fg-muted">{t('fterm_colBank')}</dt>
        <dd className="font-bold text-fg">{formatMoney(report.bank_total, language)}</dd>
        <dt className="text-fg-muted">{t('fterm_colRizqim')}</dt>
        <dd className="font-bold text-fg">
          {report.rizqim_total !== null ? formatMoney(report.rizqim_total, language) : '—'}
          {report.shared && <span className="block text-footnote font-normal text-fg-muted">{t('fterm_sharedShifts', { count: report.shifts?.length ?? 0 })}</span>}
        </dd>
        <dt className="text-fg-muted">{t('fterm_colDifference')}</dt>
        <dd className="flex flex-wrap items-center gap-2 font-bold text-fg">
          <DifferenceValue report={report} language={language} />
        </dd>
        <dt className="text-fg-muted">{t('fterm_colSource')}</dt>
        <dd className="text-fg">{t(`fterm_source_${report.source}`)}</dd>
      </dl>
    </Card>
  );
}
