'use client';

import { useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { Card, CardSkeletons, InlineError, Notice } from '@rizqim/ui';
import { useSettings, type SettingGroup, type SettingItem } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { SecretText } from './secrets';
import { defaultHint, SaveRow, SettingField, SettingSecretRow, useSettingsDraft, useSettingsSave } from './settingsForm';
import { SmsCard } from './SmsCard';

// Sozlamalar: SMS (stored here, editable: SmsCard), Telegram and Umumiy (the server's configuration, shown only). Secrets show
// "kiritilgan" and their last 4 characters; replacing one is its own action. A change asks for a reason (and the shell for a fresh code).
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
  const titleKey = GROUP_TITLE[group.key];
  const textKey = GROUP_TEXT[group.key];
  const title = titleKey ? t(titleKey) : group.label;
  const text = textKey ? t(textKey) : '';

  if (group.key === 'sms' && group.items.some((item) => item.key === 'sms_driver' && item.editable)) {
    return (
      <>
        <SmsCard group={group} title={title} text={text} onDone={onDone} />
        <ServerCard group={{ ...group, items: group.items.filter((item) => !item.editable) }} title={title} text="" />
      </>
    );
  }

  const plain = group.items.filter((item) => item.editable && !item.secret);
  const secrets = group.items.filter((item) => item.editable && item.secret);
  if (plain.length === 0 && secrets.length === 0) return <ServerCard group={group} title={title} text={text} />;

  return (
    <Card>
      <section aria-label={title} className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{title}</h2>
          {text && <p className="text-footnote text-fg-muted">{text}</p>}
        </div>
        {secrets.length > 0 && (
          <ul className="flex flex-col gap-2">
            {secrets.map((item) => (
              <li key={item.key}>
                <SettingSecretRow item={item} hint={item.description} onDone={onDone} />
              </li>
            ))}
          </ul>
        )}
        <ServerList items={group.items.filter((item) => !item.editable)} heading />
        {plain.length > 0 && <PlainForm items={plain} onDone={onDone} />}
      </section>
    </Card>
  );
}

// A card of the server's configuration only (Telegram, Umumiy): label, value, what it is; one line on where it comes from
function ServerCard({ group, title, text }: { group: SettingGroup; title: string; text: string }) {
  if (group.items.length === 0) return null;
  return (
    <Card>
      <section aria-label={title} className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{title}</h2>
          {text && <p className="text-footnote text-fg-muted">{text}</p>}
        </div>
        <ServerList items={group.items} />
      </section>
    </Card>
  );
}

function ServerList({ items, heading = false }: { items: SettingItem[]; heading?: boolean }) {
  const t = useT();
  if (items.length === 0) return null;
  return (
    <div className="flex flex-col gap-3">
      {heading && <h3 className="text-footnote font-bold text-fg-muted">{t('system_serverConfig')}</h3>}
      <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
        {items.map((item) => (
          <div key={item.key} className="flex min-w-0 flex-col">
            <dt className="text-caption font-bold text-fg-muted">{item.label}</dt>
            <dd className="break-words text-body text-fg">{item.secret ? <SecretText item={item} /> : (item.value ?? <span className="text-fg-muted">{t('system_notSet')}</span>)}</dd>
            <dd className="text-caption text-fg-muted">{item.description}</dd>
          </div>
        ))}
      </dl>
      <p className="border-t border-line pt-3 text-caption text-fg-muted">{t('system_serverConfigHint')}</p>
    </div>
  );
}

// Plain settings of a group this screen has no card of its own for: every field, then "Saqlash" at the bottom
function PlainForm({ items, onDone }: { items: SettingItem[]; onDone: (text: string) => void }) {
  const t = useT();
  const { draft, set, reset, changedIn } = useSettingsDraft(items);
  const save = useSettingsSave();
  const changed = changedIn(items.map((item) => item.key));
  return (
    <>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {items.map((item) => (
          <SettingField key={item.key} item={item} value={draft[item.key] ?? ''} hint={defaultHint(item, t)} error={fieldError(save.error, `values.${item.key}`)} onChange={(value) => set(item.key, value)} />
        ))}
      </div>
      <SaveRow items={items} changed={changed} save={save} onRevert={reset} onDone={onDone} />
    </>
  );
}
