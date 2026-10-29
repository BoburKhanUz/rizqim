'use client';

import { useState } from 'react';
import { ChevronRight, KeyRound } from 'lucide-react';
import { Button, Card, CardSkeletons, Field, fieldClass, InlineError, Notice, Pill } from '@rizqim/ui';
import { clearSecret, replaceSecret, updateSettings, useSettings, useSettingsAction, type SettingGroup, type SettingItem } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { ReasonDialog } from './parts';
import { SecretDialog, SecretText, SourceText } from './secrets';

// Sozlamalar: SMS (stored here, editable), Telegram and Umumiy (the server's configuration, shown only). Secrets show "kiritilgan"
// and their last 4 characters; replacing one is its own action. A change asks for a reason (and the shell for a fresh code).
export function SettingsTab({ onOpenPayments }: { onOpenPayments: () => void }) {
  const t = useT();
  const settings = useSettings();
  const [notice, setNotice] = useState<string | null>(null);

  if (settings.isError) return <InlineError title={t('system_loadError')} message={errorText(settings.error, t)} retryLabel={t('retry')} retrying={settings.isFetching} onRetry={() => void settings.refetch()} />;
  if (!settings.data) return <CardSkeletons count={3} lines={4} label={t('loading')} />;

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <Notice message={notice} />
      {settings.data.map((group) => (
        <GroupCard key={group.key} group={group} onDone={setNotice} />
      ))}
      <Card padded={false}>
        <button type="button" onClick={onOpenPayments} className="flex min-h-14 w-full items-center gap-3 rounded-card p-4 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
          <span className="flex min-w-0 flex-1 flex-col">
            <span className="text-body font-bold text-fg">{t('system_group_payments')}</span>
            <span className="text-footnote text-fg-muted">{t('system_group_paymentsText')}</span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </div>
  );
}

const GROUP_TITLE: Record<string, 'system_group_sms' | 'system_group_telegram' | 'system_group_general'> = { sms: 'system_group_sms', telegram: 'system_group_telegram', general: 'system_group_general' };
const GROUP_TEXT: Record<string, 'system_group_smsText' | 'system_group_telegramText' | 'system_group_generalText'> = { sms: 'system_group_smsText', telegram: 'system_group_telegramText', general: 'system_group_generalText' };

function GroupCard({ group, onDone }: { group: SettingGroup; onDone: (text: string) => void }) {
  const t = useT();
  const plain = group.items.filter((item) => item.editable && !item.secret);
  const secrets = group.items.filter((item) => item.editable && item.secret);
  const server = group.items.filter((item) => !item.editable);
  const titleKey = GROUP_TITLE[group.key];
  const textKey = GROUP_TEXT[group.key];
  const title = titleKey ? t(titleKey) : group.label;

  return (
    <Card>
      <section aria-label={title} className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{title}</h2>
          {textKey && <p className="text-footnote text-fg-muted">{t(textKey)}</p>}
        </div>
        {plain.length > 0 && <PlainForm items={plain} onDone={onDone} />}
        {secrets.map((item) => (
          <SecretRow key={item.key} item={item} onDone={onDone} />
        ))}
        {server.length > 0 && (
          <div className="flex flex-col gap-2">
            {(plain.length > 0 || secrets.length > 0) && <h3 className="text-footnote font-bold text-fg-muted">{t('system_serverConfig')}</h3>}
            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              {server.map((item) => (
                <div key={item.key} className="flex min-w-0 flex-col">
                  <dt className="text-caption font-bold text-fg-muted">{item.label}</dt>
                  <dd className="break-words text-body text-fg">{item.secret ? <SecretText item={item} /> : (item.value ?? <span className="text-fg-muted">{t('system_notSet')}</span>)}</dd>
                  <dd className="text-caption text-fg-muted">{item.description}</dd>
                </div>
              ))}
            </dl>
            <p className="text-caption text-fg-muted">{t('system_serverConfigHint')}</p>
          </div>
        )}
      </section>
    </Card>
  );
}

function PlainForm({ items, onDone }: { items: SettingItem[]; onDone: (text: string) => void }) {
  const t = useT();
  const original = Object.fromEntries(items.map((item) => [item.key, item.source === 'settings' ? (item.value ?? '') : ''])) as Record<string, string>;
  const [draft, setDraft] = useState<Record<string, string>>(original);
  // The saved values changed (after a save): the form starts from them again
  const [seen, setSeen] = useState(JSON.stringify(original));
  if (seen !== JSON.stringify(original)) {
    setSeen(JSON.stringify(original));
    setDraft(original);
  }
  const [asking, setAsking] = useState(false);
  const save = useSettingsAction(({ values, reason }: { values: Record<string, string>; reason: string }) => updateSettings(values, reason));
  const changed = Object.fromEntries(Object.entries(draft).filter(([key, value]) => value.trim() !== (original[key] ?? '').trim()));
  const dirty = Object.keys(changed).length > 0;

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {items.map((item) => (
          <Field
            key={item.key}
            label={item.label}
            hint={`${item.description}${item.source !== 'settings' && item.value ? ` ${t('system_currentValue', { value: item.value })}` : ''}`}
            error={fieldError(save.error, `values.${item.key}`)}
          >
            {(control) =>
              item.type === 'select' ? (
                <select {...control} className={fieldClass} value={draft[item.key] ?? ''} onChange={(event) => setDraft((previous) => ({ ...previous, [item.key]: event.target.value }))}>
                  <option value="">{item.default ? t('system_defaultOption', { value: item.options?.find((option) => option.value === item.default)?.label ?? item.default }) : t('system_notSet')}</option>
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
                  value={draft[item.key] ?? ''}
                  placeholder={item.default ?? ''}
                  onChange={(event) => setDraft((previous) => ({ ...previous, [item.key]: event.target.value }))}
                />
              )
            }
          </Field>
        ))}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button disabled={!dirty} onClick={() => setAsking(true)}>
          {t('system_settings_save')}
        </Button>
        {dirty && (
          <Button variant="ghost" onClick={() => setDraft(original)}>
            {t('system_revert')}
          </Button>
        )}
        {items.some((item) => item.updated_at) && <span className="text-caption text-fg-muted">{t('system_updatedAt', { time: when(items.map((item) => item.updated_at).filter(Boolean).sort().at(-1)) })}</span>}
      </div>
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

function SecretRow({ item, onDone }: { item: SettingItem; onDone: (text: string) => void }) {
  const t = useT();
  const [dialog, setDialog] = useState<'replace' | 'clear' | null>(null);
  const replace = useSettingsAction(({ value, reason }: { value: string; reason: string }) => replaceSecret(item.key, value, reason));
  const clear = useSettingsAction(({ reason }: { reason: string }) => clearSecret(item.key, reason));

  return (
    <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <KeyRound aria-hidden="true" className="size-4 text-fg-muted" />
        <span className="text-body font-bold text-fg">{item.label}</span>
        <Pill tone={item.configured ? 'success' : 'warning'}>{item.configured ? t('system_secret_set') : t('system_secret_unset')}</Pill>
      </div>
      <p className="text-body text-fg">
        <SecretText item={item} />
      </p>
      <p className="text-caption text-fg-muted">
        {item.description} <SourceText source={item.source} />
      </p>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" aria-label={t('system_secret_replaceLabel', { name: item.label })} onClick={() => setDialog('replace')}>
          {t('system_secret_replace')}
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
