'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, Field, FormError, fieldClass } from '@rizqim/ui';
import {
  bulkLineErrors,
  useCreateCorporateMember,
  useImportCorporateMembers,
  useUpdateCorporateMember,
  type CorporateBulkLineError,
  type CorporateMember,
  type CorporateMemberPatch,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal, PhoneField, phoneToE164, phoneToInput } from '../management/fields';
import { TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

const FIELDS = ['name', 'phone', 'code', 'daily_limit', 'meals_per_day', 'note', 'is_active'];
const CODE = /^[A-Za-z0-9-]{4,32}$/;

const amountText = (value: string | null | undefined) => (value ? String(Number(value)) : '');

// A typed daily limit and meals per day: the values, or the problems
function limits(daily: string, meals: string, t: ReturnType<typeof useI18n>['t']) {
  const problems: Record<string, string> = {};
  const dailyAmount = parseDecimal(daily);
  if (dailyAmount === undefined || (dailyAmount !== null && dailyAmount <= 0)) problems.daily_limit = t('corp_err_daily');
  const mealsCount = meals.trim() === '' ? null : Number(meals.trim());
  if (mealsCount !== null && (!Number.isInteger(mealsCount) || mealsCount < 1 || mealsCount > 20)) problems.meals_per_day = t('corp_err_meals');
  return {
    problems,
    daily: dailyAmount === undefined || dailyAmount === null ? null : String(dailyAmount),
    meals: mealsCount !== null && Number.isInteger(mealsCount) ? mealsCount : null,
  };
}

// Add a member of a company or change one: name, phone, talon code (generated when empty), daily limit, meals per day, the
// talon on or off, a new code for a lost chit.
export function MemberDialog({
  accountUuid,
  member,
  onClose,
  onSaved,
}: {
  accountUuid: string;
  member: CorporateMember | null;
  onClose: () => void;
  onSaved: (member: CorporateMember, created: boolean) => void;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(member?.name ?? '');
  const originalPhone = phoneToInput(member?.phone);
  const [phone, setPhone] = useState(originalPhone);
  const [code, setCode] = useState(member?.code ?? '');
  const [daily, setDaily] = useState(amountText(member?.daily_limit));
  const [meals, setMeals] = useState(member?.meals_per_day ? String(member.meals_per_day) : '');
  const [active, setActive] = useState(member?.is_active ?? true);
  const [regenerate, setRegenerate] = useState(false);
  const [note, setNote] = useState(member?.note ?? '');
  const [local, setLocal] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const create = useCreateCorporateMember(accountUuid);
  const update = useUpdateCorporateMember(accountUuid);
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const checked = limits(daily, meals, t);
    const problems: Record<string, string> = { ...checked.problems };
    if (name.trim() === '') problems.name = t('corp_err_memberName');
    const e164 = phoneToE164(phone);
    if (e164 === undefined) problems.phone = t('mgmt_phoneInvalid');
    if (code.trim() !== '' && !CODE.test(code.trim())) problems.code = t('corp_err_code');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const onError = (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t));
    if (!member) {
      create.mutate(
        {
          name: name.trim(),
          phone: e164 ?? null,
          code: code.trim() || null,
          daily_limit: checked.daily,
          meals_per_day: checked.meals,
          note: note.trim() || null,
        },
        { onSuccess: (saved) => onSaved(saved, true), onError },
      );
      return;
    }
    const patch: CorporateMemberPatch = {};
    if (name.trim() !== member.name) patch.name = name.trim();
    if (phone !== originalPhone) patch.phone = e164 ?? null;
    if (code.trim() !== '' && code.trim().toUpperCase() !== member.code) patch.code = code.trim();
    if (checked.daily !== (member.daily_limit ? String(Number(member.daily_limit)) : null)) patch.daily_limit = checked.daily;
    if (checked.meals !== member.meals_per_day) patch.meals_per_day = checked.meals;
    if (active !== member.is_active) patch.is_active = active;
    if (regenerate) patch.regenerate_code = true;
    if (note.trim() !== (member.note ?? '')) patch.note = note.trim() || null;
    if (Object.keys(patch).length === 0) return onClose();
    update.mutate({ member: member.uuid, patch }, { onSuccess: (saved) => onSaved(saved, false), onError });
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={member ? t('corp_memberEdit') : t('corp_memberNew')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('corp_memberName')} value={name} onChange={setName} maxLength={120} autoFocus error={error('name')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <PhoneField label={t('acct_phone')} value={phone} onChange={setPhone} error={error('phone')} />
          <TextField label={t('corp_code')} hint={t('corp_codeHint')} value={code} onChange={setCode} maxLength={32} disabled={regenerate} error={error('code')} />
          <NumberField label={t('corp_dailyLimit')} hint={t('corp_dailyLimitHint')} value={daily} onChange={setDaily} suffix="so'm" error={error('daily_limit')} />
          <NumberField label={t('corp_mealsPerDay')} hint={t('corp_mealsPerDayHint')} value={meals} onChange={setMeals} inputMode="numeric" error={error('meals_per_day')} />
        </div>
        {member && (
          <>
            <Check checked={active} onChange={setActive} label={t('corp_memberActive')} />
            <Check checked={regenerate} onChange={setRegenerate} label={t('corp_regenerate')} hint={t('corp_regenerateHint')} />
          </>
        )}
        <TextField label={t('acct_note')} value={note} onChange={setNote} maxLength={500} error={error('note')} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

// Paste the company's list: "name;phone" a line. All or nothing: the bad lines are listed and nobody is added.
export function BulkMembersDialog({ accountUuid, onClose, onImported }: { accountUuid: string; onClose: () => void; onImported: (count: number) => void }) {
  const { t } = useI18n();
  const [lines, setLines] = useState('');
  const [daily, setDaily] = useState('');
  const [meals, setMeals] = useState('');
  const [local, setLocal] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [bad, setBad] = useState<CorporateBulkLineError[]>([]);
  const bulk = useImportCorporateMembers(accountUuid);
  const count = lines.split(/\r?\n/).filter((line) => line.trim() !== '').length;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const checked = limits(daily, meals, t);
    const problems: Record<string, string> = { ...checked.problems };
    if (count === 0) problems.lines = t('corp_err_bulkEmpty');
    setLocal(problems);
    setErrors(NO_ERRORS);
    setBad([]);
    if (Object.keys(problems).length > 0) return;
    bulk.mutate(
      { lines, daily_limit: checked.daily, meals_per_day: checked.meals },
      {
        onSuccess: (result) => onImported(result.created),
        onError: (error) => {
          const lineErrors = bulkLineErrors(error);
          setBad(lineErrors);
          if (lineErrors.length === 0) setErrors(sortServerErrors(error, ['lines', 'daily_limit', 'meals_per_day'], t));
        },
      },
    );
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={t('corp_bulkTitle')} busy={bulk.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field label={t('corp_bulkLines')} hint={`${t('corp_bulkHint')} ${t('corp_bulkCount', { n: count })}`} error={error('lines')}>
          {(props) => (
            <textarea
              {...props}
              data-autofocus
              rows={8}
              value={lines}
              onChange={(event) => setLines(event.target.value)}
              placeholder={'Aziz Rahimov;90 111 22 33\nDilnoza Karimova;90 222 33 44'}
              className={`${fieldClass} min-h-40 font-mono`}
            />
          )}
        </Field>
        <p className="text-footnote font-bold text-fg-muted">{t('corp_bulkDefaults')}</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <NumberField label={t('corp_dailyLimit')} value={daily} onChange={setDaily} suffix="so'm" error={error('daily_limit')} />
          <NumberField label={t('corp_mealsPerDay')} value={meals} onChange={setMeals} inputMode="numeric" error={error('meals_per_day')} />
        </div>
        {bad.length > 0 && (
          <div role="alert" className="rounded-control bg-danger-soft p-3 text-footnote text-danger-soft-fg">
            <p className="font-bold">{t('corp_bulkInvalid')}</p>
            <ul className="mt-1 list-disc pl-5">
              {bad.map((line) => (
                <li key={`${line.line}-${line.reason}`}>
                  {t('corp_bulkLine', {
                    line: line.line,
                    message: line.message,
                  })}
                </li>
              ))}
            </ul>
          </div>
        )}
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={bulk.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={bulk.isPending}>
            {t('corp_bulkSubmit')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
