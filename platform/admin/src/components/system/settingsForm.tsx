'use client';

import { useId, useState, type ReactNode } from 'react';
import { CheckCircle2, CircleAlert, KeyRound } from 'lucide-react';
import { Button, Field, fieldClass, Pill } from '@rizqim/ui';
import { clearSecret, replaceSecret, updateSettings, useSettingsAction, type SettingItem } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { ReasonDialog } from './parts';
import { SecretDialog } from './secrets';

// The pieces of the Sozlamalar cards: the draft of the plain settings, one labelled field, the bottom row with "Saqlash" and the
// last change, and a secret's row (replaced or cleared on its own, never shown in full).

// The value a plain setting starts from in the form: only what is stored here (an .env value or the default is a placeholder)
const storedValue = (item: SettingItem) => (item.source === 'settings' ? (item.value ?? '') : '');

export function useSettingsDraft(items: SettingItem[]) {
  const original = Object.fromEntries(items.map((item) => [item.key, storedValue(item)])) as Record<string, string>;
  const [draft, setDraft] = useState<Record<string, string>>(original);
  // The saved values changed (after a save): the form starts from them again
  const [seen, setSeen] = useState(JSON.stringify(original));
  if (seen !== JSON.stringify(original)) {
    setSeen(JSON.stringify(original));
    setDraft(original);
  }
  return {
    draft,
    set: (key: string, value: string) => setDraft((previous) => ({ ...previous, [key]: value })),
    reset: () => setDraft(original),
    // What changed among `keys` (the fields on screen): only these are saved
    changedIn: (keys: readonly string[]) => Object.fromEntries(keys.filter((key) => (draft[key] ?? '').trim() !== (original[key] ?? '').trim()).map((key) => [key, draft[key] ?? ''])) as Record<string, string>,
  };
}

export function useSettingsSave() {
  return useSettingsAction(({ values, reason }: { values: Record<string, string>; reason: string }) => updateSettings(values, reason));
}

type SaveMutation = ReturnType<typeof useSettingsSave>;

export function SettingField({ item, value, hint, error, onChange }: { item: SettingItem; value: string; hint?: ReactNode; error?: string; onChange: (value: string) => void }) {
  const t = useT();
  const optionLabel = (option: string | null) => item.options?.find((candidate) => candidate.value === option)?.label ?? option ?? '';
  // The empty choice of a select: what applies when nothing is stored here
  const emptyLabel = item.source === 'env' && item.value ? t('system_envOption', { value: optionLabel(item.value) }) : item.default ? t('system_defaultOption', { value: optionLabel(item.default) }) : t('system_notSet');
  return (
    <Field label={item.label} hint={hint} error={error}>
      {(control) =>
        item.type === 'select' ? (
          <select {...control} className={fieldClass} value={value} onChange={(event) => onChange(event.target.value)}>
            <option value="">{emptyLabel}</option>
            {item.options?.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        ) : (
          <input
            {...control}
            type={item.type === 'email' ? 'email' : 'text'}
            autoComplete="off"
            className={fieldClass}
            value={value}
            placeholder={item.source === 'env' ? (item.value ?? '') : (item.default ?? '')}
            onChange={(event) => onChange(event.target.value)}
          />
        )
      }
    </Field>
  );
}

// The hint of a plain field when the card has no text of its own for it: the backend's description, plus the .env value it falls back to
export function defaultHint(item: SettingItem, t: ReturnType<typeof useT>): string {
  return [item.description, item.source === 'env' && item.value ? t('system_envValue', { value: item.value }) : ''].filter(Boolean).join(' ');
}

// The bottom of a settings form: "Saqlash" (asks for a reason), "Bekor qilish" while something changed, and when it last changed
export function SaveRow({ items, changed, save, onRevert, onDone }: { items: SettingItem[]; changed: Record<string, string>; save: SaveMutation; onRevert: () => void; onDone: (text: string) => void }) {
  const t = useT();
  const [asking, setAsking] = useState(false);
  const dirty = Object.keys(changed).length > 0;
  const last = items
    .map((item) => item.updated_at)
    .filter(Boolean)
    .sort()
    .at(-1);

  return (
    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4">
      <Button disabled={!dirty} onClick={() => setAsking(true)}>
        {t('system_settings_save')}
      </Button>
      {dirty && (
        <Button variant="ghost" onClick={onRevert}>
          {t('system_revert')}
        </Button>
      )}
      {last && <span className="w-full text-caption text-fg-muted sm:ms-auto sm:w-auto">{t('system_updatedAt', { time: when(last) })}</span>}
      {asking && (
        <ReasonDialog
          title={t('system_settings_reasonTitle')}
          text={t('system_settings_reasonText', { names: items.filter((item) => item.key in changed).map((item) => item.label).join(', ') })}
          submitLabel={t('system_settings_save')}
          busy={save.isPending}
          error={save.error ? errorText(save.error, t) : null}
          reasonError={fieldError(save.error, 'reason')}
          onClose={() => {
            setAsking(false);
            save.reset();
          }}
          onSubmit={(reason) =>
            save.mutate(
              { values: changed, reason },
              {
                onSuccess: () => {
                  setAsking(false);
                  onDone(t('system_settings_saved'));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}

// One secret: its state (badge with an icon, the last 4 characters), one short hint, and its own actions
export function SettingSecretRow({ item, hint, onDone }: { item: SettingItem; hint?: string; onDone: (text: string) => void }) {
  const t = useT();
  const [dialog, setDialog] = useState<'replace' | 'clear' | null>(null);
  const replace = useSettingsAction(({ value, reason }: { value: string; reason: string }) => replaceSecret(item.key, value, reason));
  const clear = useSettingsAction(({ reason }: { reason: string }) => clearSecret(item.key, reason));
  const hintId = useId();
  const tail = item.configured ? (
    item.last4 ? (
      <>
        {t('system_secret_tail')} <span className="font-mono">••••{item.last4}</span>
      </>
    ) : (
      t('system_secret_hidden')
    )
  ) : null;

  return (
    <div className="flex flex-col gap-3 rounded-control bg-surface-2 p-3 sm:flex-row sm:items-center">
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <span className="flex flex-wrap items-center gap-2">
          <KeyRound aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
          <span className="text-body font-bold text-fg">{item.label}</span>
          <Pill tone={item.configured ? 'success' : 'warning'} icon={item.configured ? CheckCircle2 : CircleAlert}>
            {item.configured ? t('system_secret_set') : t('system_secret_unset')}
          </Pill>
        </span>
        {(tail || item.source === 'env') && (
          <span className="text-footnote text-fg">
            {tail}
            {item.source === 'env' && <span className="text-fg-muted">{tail ? ' · ' : ''}{t('system_source_env')}</span>}
          </span>
        )}
        {hint && (
          <span id={hintId} className="text-footnote text-fg-muted">
            {hint}
          </span>
        )}
      </div>
      <div className="flex flex-wrap gap-2">
        <Button
          variant="secondary"
          aria-label={t(item.configured ? 'system_secret_replaceLabel' : 'system_secret_enterLabel', { name: item.label })}
          aria-describedby={hint ? hintId : undefined}
          onClick={() => setDialog('replace')}
        >
          {item.configured ? t('system_secret_replace') : t('system_secret_enter')}
        </Button>
        {item.source === 'settings' && (
          <Button variant="ghost" aria-label={t('system_secret_clearLabel', { name: item.label })} onClick={() => setDialog('clear')}>
            {t('system_secret_clear')}
          </Button>
        )}
      </div>
      {dialog === 'replace' && (
        <SecretDialog
          name={item.label}
          busy={replace.isPending}
          error={replace.error}
          onClose={() => {
            setDialog(null);
            replace.reset();
          }}
          onSubmit={(value, reason) =>
            replace.mutate(
              { value, reason },
              {
                onSuccess: () => {
                  setDialog(null);
                  onDone(t('system_secret_replaced', { name: item.label }));
                },
              },
            )
          }
        />
      )}
      {dialog === 'clear' && (
        <ReasonDialog
          title={t('system_secret_clearTitle', { name: item.label })}
          text={t('system_secret_clearText')}
          submitLabel={t('system_secret_clear')}
          tone="danger"
          busy={clear.isPending}
          error={clear.error ? errorText(clear.error, t) : null}
          reasonError={fieldError(clear.error, 'reason')}
          onClose={() => {
            setDialog(null);
            clear.reset();
          }}
          onSubmit={(reason) =>
            clear.mutate(
              { reason },
              {
                onSuccess: () => {
                  setDialog(null);
                  onDone(t('system_secret_cleared', { name: item.label }));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
