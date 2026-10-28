'use client';

import { useState } from 'react';
import { Card, CardSkeletons, Button, Field, fieldClass, FormError, InlineError, Notice, Pill, Switch } from '@rizqim/ui';
import { useRules, useUpdateRule, type AlertRule, type RuleChange } from '@/lib/api/monitoring';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { NoteDialog } from './NoteDialog';
import { severityText, severityTone } from './words';

// Qoidalar: the built-in alert rules. Each can be switched off, can send (or not send) to the Telegram group, and has its own
// thresholds. A change is saved with one button; it needs monitoring.manage, a fresh 2FA code (the shell asks) and a reason.
export function RulesTab() {
  const t = useT();
  const rules = useRules();
  const [notice, setNotice] = useState<string | null>(null);

  if (rules.isError) {
    return <InlineError title={t('monitoring_rules_loadError')} message={errorText(rules.error, t)} retryLabel={t('retry')} retrying={rules.isFetching} onRetry={() => void rules.refetch()} />;
  }
  if (!rules.data) return <CardSkeletons count={4} lines={3} label={t('loading')} />;

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <Notice message={notice} />
      <p className="text-footnote text-fg-muted">{t('monitoring_rules_intro')}</p>
      {!rules.data.telegram_configured && (
        <p role="note" className="rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">
          {t('monitoring_rules_telegramMissing')}
        </p>
      )}
      <ul aria-label={t('monitoring_rules_listLabel')} className="flex flex-col gap-3">
        {rules.data.rules.map((rule) => (
          <RuleCard key={rule.kind} rule={rule} onSaved={(label) => setNotice(t('monitoring_rules_saved', { rule: label }))} />
        ))}
      </ul>
    </div>
  );
}

interface Draft {
  enabled: boolean;
  telegram: boolean;
  threshold: string;
  threshold_secondary: string;
  window_minutes: string;
}

const draftOf = (rule: AlertRule): Draft => ({
  enabled: rule.enabled,
  telegram: rule.telegram,
  threshold: String(rule.threshold),
  threshold_secondary: rule.threshold_secondary === null ? '' : String(rule.threshold_secondary),
  window_minutes: rule.window_minutes === null ? '' : String(rule.window_minutes),
});

function RuleCard({ rule, onSaved }: { rule: AlertRule; onSaved: (label: string) => void }) {
  const t = useT();
  const { can } = useCan();
  const manage = can('monitoring.manage');
  const update = useUpdateRule();
  const [draft, setDraft] = useState<Draft>(() => draftOf(rule));
  // The saved rule changed (after a save, or from another screen): the form starts from it again
  const [seen, setSeen] = useState(() => JSON.stringify(draftOf(rule)));
  if (seen !== JSON.stringify(draftOf(rule))) {
    setSeen(JSON.stringify(draftOf(rule)));
    setDraft(draftOf(rule));
  }
  const [asking, setAsking] = useState(false);
  const [error, setError] = useState<unknown>(null);

  const numbers: Array<{ name: 'threshold' | 'threshold_secondary' | 'window_minutes'; field: NonNullable<AlertRule['fields']['threshold']>; value: string; fallback: number | null }> = [];
  numbers.push({ name: 'threshold', field: rule.fields.threshold, value: draft.threshold, fallback: rule.defaults.threshold });
  if (rule.fields.threshold_secondary) numbers.push({ name: 'threshold_secondary', field: rule.fields.threshold_secondary, value: draft.threshold_secondary, fallback: rule.defaults.threshold_secondary });
  if (rule.fields.window) numbers.push({ name: 'window_minutes', field: rule.fields.window, value: draft.window_minutes, fallback: rule.defaults.window_minutes });

  const invalid = (value: string, min: number, max: number) => !/^\d+$/.test(value) || Number(value) < min || Number(value) > max;
  const problems = numbers.filter((entry) => invalid(entry.value, entry.field.min, entry.field.max));
  const original = draftOf(rule);
  const dirty = JSON.stringify(draft) !== JSON.stringify(original);

  // Only what changed is sent
  function change(): RuleChange {
    const result: RuleChange = {};
    if (draft.enabled !== original.enabled) result.enabled = draft.enabled;
    if (draft.telegram !== original.telegram) result.telegram = draft.telegram;
    if (draft.threshold !== original.threshold) result.threshold = Number(draft.threshold);
    if (rule.fields.threshold_secondary && draft.threshold_secondary !== original.threshold_secondary) result.threshold_secondary = Number(draft.threshold_secondary);
    if (rule.fields.window && draft.window_minutes !== original.window_minutes) result.window_minutes = Number(draft.window_minutes);
    return result;
  }

  return (
    <li>
      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="w-full min-w-0 break-words text-body font-bold text-fg sm:w-auto sm:flex-1">{rule.label}</h3>
          <Pill tone={severityTone(rule.severity)}>{severityText(rule.severity, t)}</Pill>
          {rule.unresolved_alerts > 0 && <Pill tone="warning">{t('monitoring_rules_openAlerts', { count: rule.unresolved_alerts })}</Pill>}
        </div>
        <p className="text-footnote text-fg-muted">{rule.description}</p>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="flex items-center justify-between gap-3 rounded-control bg-surface-2 px-3 py-1">
            <span className="text-footnote font-bold text-fg">{t('monitoring_rules_enabled')}</span>
            <Switch checked={draft.enabled} disabled={!manage} label={t('monitoring_rules_enabledLabel', { rule: rule.label })} onChange={(enabled) => setDraft((previous) => ({ ...previous, enabled }))} />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-control bg-surface-2 px-3 py-1">
            <span className="text-footnote font-bold text-fg">{t('monitoring_rules_telegram')}</span>
            <Switch checked={draft.telegram} disabled={!manage} label={t('monitoring_rules_telegramLabel', { rule: rule.label })} onChange={(telegram) => setDraft((previous) => ({ ...previous, telegram }))} />
          </div>
        </div>
        {!draft.enabled && rule.enabled && <p className="text-footnote text-fg-muted">{t('monitoring_rules_offNote')}</p>}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {numbers.map((entry) => {
            const bad = problems.includes(entry);
            return (
              <Field
                key={entry.name}
                label={`${entry.field.label} (${entry.field.unit})`}
                hint={entry.fallback !== null ? t('monitoring_rules_default', { value: entry.fallback, unit: entry.field.unit }) : undefined}
                error={bad ? t('monitoring_rules_invalid', { min: entry.field.min, max: entry.field.max }) : fieldError(error, entry.name)}
              >
                {(control) => (
                  <input
                    {...control}
                    type="number"
                    inputMode="numeric"
                    min={entry.field.min}
                    max={entry.field.max}
                    value={entry.value}
                    disabled={!manage}
                    onChange={(event) => setDraft((previous) => ({ ...previous, [entry.name]: event.target.value }))}
                    className={fieldClass}
                  />
                )}
              </Field>
            );
          })}
        </div>

        {rule.updated_by && <p className="text-caption text-fg-muted">{t('monitoring_rules_changedBy', { name: rule.updated_by.name, time: when(rule.updated_at) })}</p>}
        {manage ? (
          <div className="flex flex-wrap gap-2">
            <Button aria-label={t('monitoring_rules_saveLabel', { rule: rule.label })} disabled={!dirty || problems.length > 0} onClick={() => setAsking(true)}>
              {t('monitoring_rules_save')}
            </Button>
            {dirty && (
              <Button variant="ghost" onClick={() => setDraft(original)}>
                {t('monitoring_rules_revert')}
              </Button>
            )}
          </div>
        ) : (
          <p className="text-footnote text-fg-muted">{t('monitoring_viewOnly')}</p>
        )}
        <FormError message={!asking && error ? errorText(error, t) : null} />
      </Card>

      {asking && (
        <NoteDialog
          title={t('monitoring_rules_reasonTitle')}
          text={t('monitoring_rules_reasonText', { rule: rule.label })}
          label={t('monitoring_reason_label')}
          placeholder={t('monitoring_reason_placeholder')}
          confirmLabel={t('monitoring_rules_save')}
          required
          minLength={3}
          busy={update.isPending}
          error={error ? errorText(error, t) : null}
          onClose={() => {
            setAsking(false);
            setError(null);
          }}
          onSubmit={(reason) => {
            setError(null);
            update.mutate(
              { kind: rule.kind, change: change(), reason },
              {
                onSuccess: () => {
                  setAsking(false);
                  onSaved(rule.label);
                },
                onError: (failure) => setError(failure),
              },
            );
          }}
        />
      )}
    </li>
  );
}
