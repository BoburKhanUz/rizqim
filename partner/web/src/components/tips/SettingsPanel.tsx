'use client';

import { useState } from 'react';
import { Button, Card, CardSkeletons, Field, fieldClass, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { SelectField } from '../pos/fields';
import { parseAmountInput } from '../staffpay/describe';
import type { PoolPeriod, PoolRule, TipMode, TipSettingsPage } from './api';
import { useSaveTipSettings, useTipSettings } from './hooks';

// "Sozlamalar": how the branch shares its tips (owner decision 29.10.2026) — personal (the order's waiter, or the
// one picked at payment) or a shared pool split per business day or shift equally, by hours, by sales or by the
// weights of the roles taking part. Changing it needs the `tips` module (the "Format" page).
export function SettingsPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const settings = useTipSettings(branchId);

  if (settings.isPending) return <CardSkeletons count={1} lines={4} label={t('loading')} />;
  if (settings.isError) {
    return <InlineError title={t('loadErrorTitle')} message={describeError(settings.error, t).message} retryLabel={t('retry')} retrying={settings.isFetching} onRetry={() => void settings.refetch()} />;
  }

  return <SettingsForm branchId={branchId} page={settings.data} />;
}

function SettingsForm({ branchId, page }: { branchId: number; page: TipSettingsPage }) {
  const { t } = useI18n();
  const [saved, setSaved] = useState(false);
  const save = useSaveTipSettings(branchId, { onSuccess: () => setSaved(true) });
  const [mode, setMode] = useState<TipMode>(page.settings.mode);
  const [rule, setRule] = useState<PoolRule>(page.settings.pool_rule);
  const [period, setPeriod] = useState<PoolPeriod>(page.settings.pool_period);
  const [roles, setRoles] = useState<string[]>(page.settings.roles);
  const [weights, setWeights] = useState<Record<string, string>>(() => Object.fromEntries(Object.entries(page.settings.role_weights).map(([role, weight]) => [role, weight.replace(/\.?0+$/, '')])));
  const [problem, setProblem] = useState<string | null>(null);
  const disabled = !page.settings.enabled;
  const failure = save.error ? describeError(save.error, t) : null;

  const toggle = (role: string) => setRoles((current) => (current.includes(role) ? current.filter((item) => item !== role) : [...current, role]));

  const submit = () => {
    setSaved(false);
    if (mode === 'pool' && roles.length === 0) {
      setProblem(t('tips_err_roles'));
      return;
    }
    const parsed: Record<string, string> = {};
    for (const role of roles) {
      const value = parseAmountInput(weights[role] ?? '1');
      if (value === null || Number(value) <= 0) {
        setProblem(t('tips_err_weight'));
        return;
      }
      parsed[role] = value;
    }
    setProblem(null);
    save.mutate({ mode, pool_rule: rule, pool_period: period, roles: roles.length > 0 ? roles : ['waiter'], ...(rule === 'role_weights' ? { role_weights: parsed } : {}) });
  };

  const option = (items: { key: string; label: string }[]) => items.map((item) => ({ value: item.key, label: item.label }));

  return (
    <Card className="flex max-w-2xl flex-col gap-4">
      {disabled && <ErrorBanner message={t('tips_moduleOff')} />}
      <SelectField label={t('tips_mode')} value={mode} onChange={setMode} options={option(page.options.modes) as Array<{ value: TipMode; label: string }>} disabled={disabled} hint={t(mode === 'pool' ? 'tips_modePoolHint' : 'tips_modePersonalHint')} />
      {mode === 'pool' && (
        <>
          <SelectField label={t('tips_rule')} value={rule} onChange={setRule} options={option(page.options.pool_rules) as Array<{ value: PoolRule; label: string }>} disabled={disabled} hint={t(`tips_ruleHint_${rule}`)} />
          <SelectField label={t('tips_period')} value={period} onChange={setPeriod} options={option(page.options.pool_periods) as Array<{ value: PoolPeriod; label: string }>} disabled={disabled} />
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('tips_roles')}</legend>
            {page.options.roles.map((role) => (
              <div key={role.key} className="flex flex-wrap items-center gap-3">
                <label className="flex min-h-11 min-w-40 items-center gap-2 text-body">
                  <input type="checkbox" className="size-5" checked={roles.includes(role.key)} disabled={disabled} onChange={() => toggle(role.key)} />
                  {role.label}
                </label>
                {rule === 'role_weights' && roles.includes(role.key) && (
                  <Field label={t('tips_weightOf', { role: role.label })} className="w-36">
                    {(control) => <input {...control} inputMode="decimal" className={fieldClass} disabled={disabled} value={weights[role.key] ?? '1'} onChange={(event) => setWeights((current) => ({ ...current, [role.key]: event.target.value }))} />}
                  </Field>
                )}
              </div>
            ))}
          </fieldset>
        </>
      )}
      <p className="text-footnote text-fg-muted">{t('tips_notRevenue')}</p>
      {problem && <ErrorBanner message={problem} />}
      {failure && <ErrorBanner message={failure.message} />}
      {saved && <SuccessNote>{t('tips_saved')}</SuccessNote>}
      <div>
        <Button loading={save.isPending} disabled={disabled} onClick={submit}>
          {t('tips_save')}
        </Button>
      </div>
    </Card>
  );
}
