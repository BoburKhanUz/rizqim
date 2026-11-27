'use client';

import { CheckCircle2, CircleAlert, FlaskConical, PencilLine, type LucideIcon } from 'lucide-react';
import { Card, cx, Pill } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import type { SettingGroup, SettingItem } from '@/lib/api/system';
import { fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { defaultHint, SaveRow, SettingField, SettingSecretRow, useSettingsDraft, useSettingsSave } from './settingsForm';

// The SMS card: how codes are sent (sms_driver) and only what that method needs (SystemSettings on the backend, read by
// SmsDriver / EskizSmsService / OnlinegroupSmsService). On top, whether SMS really goes out with what is SAVED; a method chosen but not
// saved yet shows the new method's fields (its key can be entered before switching: the backend refuses Onlinegroup without one)
// and says the line above is still the saved state. Fields of the other methods are hidden, not cleared, and are not sent.

const DRIVER = 'sms_driver';

// What each method reads. Onlinegroup does not use eskiz_from (OnlinegroupSmsService sends no sender name).
const METHODS: Record<string, { fields: readonly string[]; secrets: readonly string[] }> = {
  log: { fields: [], secrets: [] },
  eskiz: { fields: ['eskiz_email', 'eskiz_from'], secrets: ['eskiz_password'] },
  onlinegroup: { fields: [], secrets: ['onlinegroup_api_key'] },
};
const KNOWN = new Set([DRIVER, ...Object.values(METHODS).flatMap((method) => [...method.fields, ...method.secrets])]);
const METHOD_NAME: Record<string, string> = { log: 'Log', eskiz: 'Eskiz', onlinegroup: 'Onlinegroup' };
const METHOD_HINT: Record<string, MessageKey> = { log: 'system_sms_hint_log', eskiz: 'system_sms_hint_eskiz', onlinegroup: 'system_sms_hint_onlinegroup' };
const FIELD_HINT: Record<string, MessageKey> = { eskiz_email: 'system_sms_hint_eskizEmail', eskiz_password: 'system_sms_hint_eskizPassword', onlinegroup_api_key: 'system_sms_hint_onlinegroupKey' };

type Tone = 'success' | 'warning' | 'neutral';
const TONE: Record<Tone, string> = {
  success: 'bg-success-soft text-success-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  neutral: 'bg-surface-2 text-fg',
};
const ICON: Record<Tone, LucideIcon> = { success: CheckCircle2, warning: CircleAlert, neutral: FlaskConical };

const filled = (value: string | null | undefined) => (value ?? '').trim() !== '';

// Whether SMS goes out with the saved settings, from what GET /system/settings answers
export function smsReadiness(items: SettingItem[]): { tone: Tone; title: MessageKey; params?: Record<string, string>; noCodes: boolean } {
  const byKey = new Map(items.map((item) => [item.key, item]));
  const driver = byKey.get(DRIVER);
  const method = driver?.value || driver?.default || 'log';
  if (method === 'log') return { tone: 'neutral', title: 'system_sms_testMode', noCodes: true };
  if (method === 'onlinegroup') {
    return byKey.get('onlinegroup_api_key')?.configured ? { tone: 'success', title: 'system_sms_ready', params: { method: METHOD_NAME.onlinegroup! }, noCodes: false } : { tone: 'warning', title: 'system_sms_onlinegroupMissing', noCodes: true };
  }
  if (method === 'eskiz') {
    const email = filled(byKey.get('eskiz_email')?.value);
    const password = Boolean(byKey.get('eskiz_password')?.configured);
    if (email && password) return { tone: 'success', title: 'system_sms_ready', params: { method: METHOD_NAME.eskiz! }, noCodes: false };
    return { tone: 'warning', title: !email && !password ? 'system_sms_eskizMissingBoth' : !email ? 'system_sms_eskizMissingEmail' : 'system_sms_eskizMissingPassword', noCodes: true };
  }
  return { tone: 'warning', title: 'system_sms_unknown', params: { value: method }, noCodes: true };
}

export function SmsCard({ group, title, text, onDone }: { group: SettingGroup; title: string; text: string; onDone: (text: string) => void }) {
  const t = useT();
  const items = group.items.filter((item) => item.editable);
  const byKey = new Map(items.map((item) => [item.key, item]));
  const driver = byKey.get(DRIVER)!;
  const { draft, set, reset, changedIn } = useSettingsDraft(items.filter((item) => !item.secret));
  const save = useSettingsSave();

  // The method on screen: the one chosen in the form, or what applies when nothing is stored here
  const chosen = draft[DRIVER] || (driver.source === 'env' ? driver.value : driver.default) || 'log';
  const method = METHODS[chosen] ?? { fields: [], secrets: [] };
  // Settings this card does not know (a newer backend) are always shown
  const extra = items.filter((item) => !KNOWN.has(item.key));
  const plain = [...method.fields.map((key) => byKey.get(key)), ...extra.filter((item) => !item.secret)].filter((item): item is SettingItem => Boolean(item));
  const secrets = [...method.secrets.map((key) => byKey.get(key)), ...extra.filter((item) => item.secret)].filter((item): item is SettingItem => Boolean(item));
  const changed = changedIn([DRIVER, ...plain.map((item) => item.key)]);
  const dirty = Object.keys(changed).length > 0;

  const ready = smsReadiness(items);
  const Icon = ICON[ready.tone];

  const hintFor = (item: SettingItem) => {
    if (item.key === 'eskiz_from') return t('system_sms_hint_eskizFrom', { value: (item.source === 'env' ? item.value : item.default) ?? '4546' });
    const key = FIELD_HINT[item.key];
    return key ? t(key) : defaultHint(item, t);
  };

  return (
    <Card>
      <section aria-label={title} className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{title}</h2>
          <p className="text-footnote text-fg-muted">{text}</p>
        </div>

        <div className="flex flex-col gap-2">
          <div role="status" className={cx('flex items-start gap-2.5 rounded-control px-3 py-2.5', TONE[ready.tone])}>
            <Icon aria-hidden="true" className="mt-px size-4 shrink-0" />
            <p className="min-w-0 text-footnote">
              <span className="font-bold">
                {t(ready.title, ready.params)}
                {ready.noCodes && '.'}
              </span>
              {ready.noCodes && <span className={ready.tone === 'neutral' ? 'text-fg-muted' : undefined}> {t('system_sms_noCodes')}</span>}
            </p>
          </div>
          {dirty && (
            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-footnote text-fg-muted">
              <Pill tone="warning" icon={PencilLine}>
                {t('system_unsavedPill')}
              </Pill>
              {t('system_sms_unsavedHint')}
            </p>
          )}
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SettingField item={driver} value={draft[DRIVER] ?? ''} hint={METHOD_HINT[chosen] ? t(METHOD_HINT[chosen]) : defaultHint(driver, t)} error={fieldError(save.error, `values.${DRIVER}`)} onChange={(value) => set(DRIVER, value)} />
          {plain.length > 0 && <div aria-hidden="true" className="hidden sm:block" />}
          {plain.map((item) => (
            <SettingField key={item.key} item={item} value={draft[item.key] ?? ''} hint={hintFor(item)} error={fieldError(save.error, `values.${item.key}`)} onChange={(value) => set(item.key, value)} />
          ))}
        </div>

        {secrets.length > 0 && (
          <ul className="flex flex-col gap-2">
            {secrets.map((item) => (
              <li key={item.key}>
                <SettingSecretRow item={item} hint={hintFor(item)} onDone={onDone} />
              </li>
            ))}
          </ul>
        )}

        <SaveRow items={items} changed={changed} save={save} onRevert={reset} onDone={onDone} />
      </section>
    </Card>
  );
}
