'use client';

import { useState } from 'react';
import { Plus, Trash2, Wallet } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { describeError, unplacedMessages } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';
import { COMPONENT_TYPES, PERIODS, type ComponentType, type PayComponent, type PayPeriod, type PayRule, type RulesPage } from './api';
import { describeComponent, parseAmountInput, todayIso } from './describe';
import { useDeleteRule, useRules, useSavePeriod, useSaveRule } from './hooks';

// "Ish haqi qoidalari": the branch's pay period and its wage rules, one version per person or role and start date
export function RulesPanel({ branchId, canManage }: { branchId: number; canManage: boolean }) {
  const { t, language } = useI18n();
  const rules = useRules(branchId);
  const [editing, setEditing] = useState<PayRule | 'new' | null>(null);
  const [deleting, setDeleting] = useState<PayRule | null>(null);
  const remove = useDeleteRule({ onSuccess: () => setDeleting(null) });

  if (rules.isPending) return <CardSkeletons count={2} lines={3} label={t('loading')} />;
  if (rules.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(rules.error, t).message} retryLabel={t('retry')} retrying={rules.isFetching} onRetry={() => void rules.refetch()} />;
  const page = rules.data;

  return (
    <div className="flex flex-col gap-4">
      <PeriodCard key={page.period} page={page} branchId={branchId} canManage={canManage} />

      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-headline font-bold text-fg">{t('sp_rulesTitle')}</h2>
            <p className="text-footnote text-fg-muted">{t('sp_rulesHint')}</p>
          </div>
          {canManage && (
            <Button icon={Plus} onClick={() => setEditing('new')}>
              {t('sp_ruleAdd')}
            </Button>
          )}
        </div>
        {page.rules.length === 0 ? (
          <EmptyState icon={Wallet} title={t('sp_rulesEmptyTitle')} text={t('sp_rulesEmptyText')} />
        ) : (
          <DataTable caption={t('sp_rulesTitle')}>
            <thead>
              <tr>
                <Th>{t('sp_colWho')}</Th>
                <Th>{t('sp_colFrom')}</Th>
                <Th>{t('sp_colParts')}</Th>
                {canManage && <Th />}
              </tr>
            </thead>
            <tbody>
              {page.rules.map((rule) => (
                <tr key={rule.uuid}>
                  <Td>
                    <span className="font-bold">{rule.user_id !== null ? rule.user_name : t('sp_roleRule', { role: rule.role_label ?? rule.role ?? '' })}</span>
                    {rule.note ? <span className="block text-caption text-fg-muted">{rule.note}</span> : null}
                  </Td>
                  <Td className="whitespace-nowrap">{formatDay(rule.effective_from, language)}</Td>
                  <Td>
                    {rule.components.length === 0 ? (
                      <span className="text-fg-muted">{t('sp_noPay')}</span>
                    ) : (
                      <ul className="flex flex-col gap-0.5">
                        {rule.components.map((component) => (
                          <li key={component.type}>{describeComponent(component, t, language)}</li>
                        ))}
                      </ul>
                    )}
                  </Td>
                  {canManage && (
                    <Td className="whitespace-nowrap text-right">
                      <Button variant="secondary" onClick={() => setEditing(rule)}>
                        {t('sp_edit')}
                      </Button>{' '}
                      <Button variant="ghost" icon={Trash2} onClick={() => setDeleting(rule)} aria-label={t('sp_ruleDelete')}>
                        {t('sp_delete')}
                      </Button>
                    </Td>
                  )}
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </Card>

      {editing !== null && <RuleDialog page={page} branchId={branchId} rule={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      {deleting !== null && (
        <ConfirmDialog
          title={t('sp_ruleDeleteTitle')}
          text={t('sp_ruleDeleteText', { from: formatDay(deleting.effective_from, language) })}
          confirmLabel={t('sp_delete')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(deleting.uuid)}
          onClose={() => setDeleting(null)}
        />
      )}
    </div>
  );
}

function PeriodCard({ page, branchId, canManage }: { page: RulesPage; branchId: number; canManage: boolean }) {
  const { t } = useI18n();
  const [period, setPeriod] = useState<PayPeriod>(page.period);
  const save = useSavePeriod();

  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-headline font-bold text-fg">{t('sp_periodTitle')}</h2>
      <p className="text-footnote text-fg-muted">{t('sp_periodHint')}</p>
      <div className="flex flex-wrap items-end gap-3">
        <Field label={t('sp_period')} className="min-w-52">
          {(control) => (
            <select {...control} className={fieldClass} value={period} disabled={!canManage} onChange={(event) => setPeriod(event.target.value as PayPeriod)}>
              {PERIODS.map((value) => (
                <option key={value} value={value}>
                  {t(`sp_period_${value}`)}
                </option>
              ))}
            </select>
          )}
        </Field>
        {canManage && (
          <Button variant="secondary" loading={save.isPending} disabled={period === page.period} onClick={() => save.mutate({ branchId, period })}>
            {t('sp_save')}
          </Button>
        )}
      </div>
      {save.error ? <ErrorBanner message={describeError(save.error, t).message} /> : null}
    </Card>
  );
}

// A component as the form edits it: every field a string
interface Draft {
  key: string;
  type: ComponentType;
  amount: string;
  percent: string;
  per: 'shift' | 'period';
  mode: 'max' | 'plus';
  base: 'sales' | 'service_charge';
  eventTypes: string[];
}

const USES_AMOUNT: ComponentType[] = ['shift_rate', 'hourly_rate', 'guarantee', 'event_rate'];
const USES_PERCENT: ComponentType[] = ['sales_percent', 'service_charge_percent', 'guarantee'];

let draftSeq = 0;
const toDraft = (component: Partial<PayComponent> & { type: ComponentType }): Draft => ({
  key: `c${++draftSeq}`,
  type: component.type,
  amount: component.amount ? String(Number(component.amount)) : '',
  percent: component.percent ? String(Number(component.percent)) : '',
  per: component.per ?? 'shift',
  mode: component.mode ?? 'max',
  base: component.base ?? 'sales',
  eventTypes: component.event_types ?? [],
});

function RuleDialog({ page, branchId, rule, onClose }: { page: RulesPage; branchId: number; rule: PayRule | null; onClose: () => void }) {
  const { t } = useI18n();
  const save = useSaveRule({ onSuccess: onClose });
  const [subject, setSubject] = useState<'role' | 'user'>(rule?.user_id ? 'user' : 'role');
  const [role, setRole] = useState(rule?.role ?? page.roles.find((option) => option.value === 'waiter')?.value ?? page.roles[0]?.value ?? '');
  const [userId, setUserId] = useState<number | null>(rule?.user_id ?? page.staff[0]?.id ?? null);
  const [from, setFrom] = useState(rule?.effective_from ?? todayIso());
  const [note, setNote] = useState(rule?.note ?? '');
  const [parts, setParts] = useState<Draft[]>(() => (rule ? rule.components.map(toDraft) : [toDraft({ type: 'shift_rate' })]));
  const [problem, setProblem] = useState<Record<string, string>>({});
  const failure = save.error ? describeError(save.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];
  const unused = COMPONENT_TYPES.filter((type) => !parts.some((part) => part.type === type));

  const change = (key: string, patch: Partial<Draft>) => setParts((list) => list.map((part) => (part.key === key ? { ...part, ...patch } : part)));

  const submit = () => {
    const found: Record<string, string> = {};
    const components: PayComponent[] = [];
    parts.forEach((part, index) => {
      const component: PayComponent = { type: part.type };
      if (USES_AMOUNT.includes(part.type)) {
        const amount = parseAmountInput(part.amount);
        if (amount === null || amount.startsWith('-')) found[`components.${index}.amount`] = t('sp_errAmount');
        else component.amount = amount;
      }
      if (USES_PERCENT.includes(part.type)) {
        const percent = part.percent.trim() === '' && part.type === 'guarantee' ? '0' : parseAmountInput(part.percent);
        if (percent === null || Number(percent) < 0 || Number(percent) > 100) found[`components.${index}.percent`] = t('sp_errPercent');
        else component.percent = percent;
      }
      if (part.type === 'guarantee') Object.assign(component, { per: part.per, mode: part.mode, base: part.base });
      if (part.type === 'event_rate') component.event_types = part.eventTypes;
      components.push(component);
    });
    if (from === '') found.effective_from = t('menu_err_required');
    if (rule === null && subject === 'user' && userId === null) found.user_id = t('menu_err_required');
    setProblem(found);
    if (Object.keys(found).length > 0) return;
    save.mutate({
      uuid: rule?.uuid ?? null,
      branchId,
      userId: subject === 'user' ? userId : null,
      role: subject === 'role' ? role : null,
      effectiveFrom: from,
      components,
      note: note.trim() === '' ? null : note.trim(),
    });
  };

  const leftover = failure ? unplacedMessages(failure.fields, /^(components\.\d+\.(amount|percent)|effective_from|user_id)$/) : [];

  return (
    <Dialog
      open
      onClose={onClose}
      title={rule === null ? t('sp_ruleNew') : t('sp_ruleEdit')}
      description={t('sp_ruleDialogHint')}
      busy={save.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            {t('sp_save')}
          </Button>
        </>
      }
    >
      {rule === null ? (
        <fieldset className="flex flex-col gap-3">
          <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('sp_ruleFor')}</legend>
          <div className="flex flex-wrap gap-4">
            {(['role', 'user'] as const).map((kind) => (
              <label key={kind} className="flex min-h-11 cursor-pointer items-center gap-2 text-body text-fg">
                <input type="radio" name="sp-subject" className="size-5 accent-[var(--color-brand)]" checked={subject === kind} onChange={() => setSubject(kind)} />
                {t(kind === 'role' ? 'sp_forRole' : 'sp_forPerson')}
              </label>
            ))}
          </div>
          {subject === 'role' ? (
            <Field label={t('sp_role')} error={fieldError('role')}>
              {(control) => (
                <select {...control} className={fieldClass} value={role} onChange={(event) => setRole(event.target.value)}>
                  {page.roles.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          ) : (
            <Field label={t('sp_person')} error={fieldError('user_id')}>
              {(control) => (
                <select {...control} className={fieldClass} value={userId ?? ''} onChange={(event) => setUserId(event.target.value === '' ? null : Number(event.target.value))}>
                  {page.staff.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.name} · {member.role_label}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          )}
        </fieldset>
      ) : (
        <p className="text-body font-bold text-fg">{rule.user_id !== null ? rule.user_name : t('sp_roleRule', { role: rule.role_label ?? rule.role ?? '' })}</p>
      )}

      <DateField label={t('sp_effectiveFrom')} value={from} onChange={setFrom} error={fieldError('effective_from')} hint={t('sp_effectiveFromHint')} />

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('sp_parts')}</legend>
        {parts.length === 0 && <p className="text-footnote text-fg-muted">{t('sp_noPartsHint')}</p>}
        {parts.map((part, index) => (
          <div key={part.key} className="flex flex-col gap-3 rounded-control border border-line p-3" data-testid="sp-part">
            <div className="flex items-end gap-2">
              <Field label={t('sp_partType')} className="flex-1">
                {(control) => (
                  <select {...control} className={fieldClass} value={part.type} onChange={(event) => change(part.key, { type: event.target.value as ComponentType })}>
                    {COMPONENT_TYPES.filter((type) => type === part.type || unused.includes(type)).map((type) => (
                      <option key={type} value={type}>
                        {t(`sp_type_${type}`)}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
              <Button variant="ghost" icon={Trash2} aria-label={t('sp_partRemove')} onClick={() => setParts((list) => list.filter((item) => item.key !== part.key))}>
                {t('sp_partRemove')}
              </Button>
            </div>
            <p className="text-caption text-fg-muted">{t(`sp_typeHint_${part.type}`)}</p>
            <div className="flex flex-wrap gap-3">
              {USES_AMOUNT.includes(part.type) && (
                <Field label={t(part.type === 'hourly_rate' ? 'sp_amountHour' : part.type === 'event_rate' ? 'sp_amountEvent' : part.type === 'guarantee' ? 'sp_amountGuarantee' : 'sp_amountShift')} error={fieldError(`components.${index}.amount`)}>
                  {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={part.amount} onChange={(event) => change(part.key, { amount: event.target.value })} />}
                </Field>
              )}
              {USES_PERCENT.includes(part.type) && (
                <Field label={t('sp_percent')} error={fieldError(`components.${index}.percent`)}>
                  {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={part.percent} onChange={(event) => change(part.key, { percent: event.target.value })} />}
                </Field>
              )}
            </div>
            {part.type === 'guarantee' && (
              <div className="flex flex-wrap gap-3">
                <Field label={t('sp_guaranteePer')}>
                  {(control) => (
                    <select {...control} className={fieldClass} value={part.per} onChange={(event) => change(part.key, { per: event.target.value as Draft['per'] })}>
                      {page.guarantee_per.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  )}
                </Field>
                <Field label={t('sp_guaranteeMode')}>
                  {(control) => (
                    <select {...control} className={fieldClass} value={part.mode} onChange={(event) => change(part.key, { mode: event.target.value as Draft['mode'] })}>
                      {page.guarantee_modes.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  )}
                </Field>
                <Field label={t('sp_guaranteeBase')}>
                  {(control) => (
                    <select {...control} className={fieldClass} value={part.base} onChange={(event) => change(part.key, { base: event.target.value as Draft['base'] })}>
                      {page.percent_bases.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  )}
                </Field>
              </div>
            )}
            {part.type === 'event_rate' && (
              <fieldset className="flex flex-wrap gap-3">
                <legend className="mb-1 text-footnote text-fg-muted">{t('sp_eventTypes')}</legend>
                {page.event_types
                  .filter((option) => option.value !== 'other')
                  .map((option) => (
                    <label key={option.value} className="flex min-h-11 cursor-pointer items-center gap-2 text-body text-fg">
                      <input
                        type="checkbox"
                        className="size-5 accent-[var(--color-brand)]"
                        checked={part.eventTypes.includes(option.value)}
                        onChange={(event) => change(part.key, { eventTypes: event.target.checked ? [...part.eventTypes, option.value] : part.eventTypes.filter((value) => value !== option.value) })}
                      />
                      {option.label}
                    </label>
                  ))}
              </fieldset>
            )}
          </div>
        ))}
        {unused.length > 0 && (
          <div>
            <Button variant="secondary" icon={Plus} onClick={() => setParts((list) => [...list, toDraft({ type: unused[0]! })])}>
              {t('sp_partAdd')}
            </Button>
          </div>
        )}
      </fieldset>

      <Field label={t('sp_note')}>{(control) => <input {...control} className={fieldClass} maxLength={500} value={note} onChange={(event) => setNote(event.target.value)} />}</Field>
      {failure && <ErrorBanner message={failure.message} details={leftover} />}
    </Dialog>
  );
}
