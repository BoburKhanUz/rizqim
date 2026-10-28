'use client';

import { useState } from 'react';
import { Check as CheckIcon, Copy, ExternalLink } from 'lucide-react';
import { Button, Card, Field, Section, buttonClasses, fieldClass, Pill } from '@rizqim/ui';
import type { StoreSettings } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';

// P4-32 (the old store panel's Telegram tab): the bot link that sends the store's orders and notices to a Telegram group. The
// owner (or a store-wide manager) adds the bot to the group and opens the link there; the bot refuses anyone else. Linking
// another group moves the messages there (the old group is told).
export function TelegramGroupCard({ settings }: { settings: StoreSettings }) {
  const { t } = useI18n();
  const [copied, setCopied] = useState(false);
  const [copyFailed, setCopyFailed] = useState(false);
  const telegram = settings.telegram;
  const link = telegram?.group_link ?? null;

  const copy = async () => {
    if (!link) return;
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
      setCopyFailed(false);
    } catch {
      setCopyFailed(true);
    }
  };

  return (
    <Section title={t('settings_telegramSection')}>
      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Pill tone={telegram?.group_linked ? 'success' : 'warning'}>{telegram?.group_linked ? t('settings_telegramLinked') : t('settings_telegramNotLinked')}</Pill>
        </div>
        <p className="text-footnote text-fg-muted">{t('settings_telegramHint')}</p>
        {link ? (
          <>
            <Field label={t('settings_telegramLink')}>{(props) => <input {...props} readOnly className={fieldClass} value={link} onFocus={(event) => event.target.select()} />}</Field>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" icon={copied ? CheckIcon : Copy} onClick={() => void copy()}>
                {copied ? t('mgmt_copied') : t('mgmt_copy')}
              </Button>
              <a href={link} target="_blank" rel="noreferrer" className={buttonClasses({ variant: 'ghost' })}>
                <ExternalLink aria-hidden="true" className="size-4" />
                {t('settings_telegramOpen')}
              </a>
            </div>
            {copyFailed && <p role="alert" className="text-footnote text-danger-soft-fg">{t('mgmt_copyFailed')}</p>}
          </>
        ) : (
          <p className="text-footnote text-fg-muted">{t('settings_telegramNoBot')}</p>
        )}
      </Card>
    </Section>
  );
}
