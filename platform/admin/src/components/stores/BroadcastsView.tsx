'use client';

import { useState } from 'react';
import { Megaphone, Send, ShieldAlert, X } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, Field, fieldClass, FormError, IconButton, InlineError, Notice, PageHeader, Pill } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { useBroadcasts, usePreviewBroadcast, useSendBroadcast, type BroadcastAudience, type BroadcastPreview } from '@/lib/api/stores';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import type { MessageKey } from '@/i18n';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { StorePicker } from './StorePicker';
import { StoresNav } from './StoresNav';

// Ommaviy e'lon (P4-22): a Telegram message to the owners of all, the active or selected stores. The member first looks at
// the audience ("Ko'rib chiqish": how many stores, how many owners get it, how many have no Telegram), then sends to exactly
// that many (the backend refuses if the count changed meanwhile); a reason and the step-up as for every action. Limited per
// member and per day.

type Audience = 'all' | 'active' | 'selected';

const STATUS_LABEL: Record<string, MessageKey> = {
  queued: 'stores_broadcastStatus_queued',
  sending: 'stores_broadcastStatus_sending',
  sent: 'stores_broadcastStatus_sent',
  failed: 'stores_broadcastStatus_failed',
};

export function BroadcastsView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const list = useBroadcasts(allowed);
  const [notice, setNotice] = useState<string | null>(null);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const limit = list.data?.limit_per_day ?? 10;
  return (
    <>
      <PageHeader title={t('stores_broadcastsTitle')} subtitle={t('stores_broadcastsSubtitle', { limit })} />
      <StoresNav current="broadcasts" />
      <Notice message={notice} />
      <div className="flex max-w-3xl flex-col gap-5">
        {can('stores.manage') && <Composer onSent={(count) => setNotice(t('stores_broadcastDone', { count }))} />}
        {list.isError ? (
          <InlineError title={t('stores_broadcastsLoadError')} message={errorText(list.error, t)} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />
        ) : !list.data ? (
          <CardSkeletons count={2} lines={2} label={t('loading')} />
        ) : (
          <>
            <p className="text-footnote text-fg-muted">{t('stores_broadcastToday', { count: list.data.sent_today, limit })}</p>
            {list.data.broadcasts.length === 0 ? (
              <EmptyState icon={Megaphone} title={t('stores_broadcastsEmpty')} />
            ) : (
              <ul aria-label={t('stores_broadcastsTitle')} className="flex flex-col gap-2">
                {list.data.broadcasts.map((broadcast) => (
                  <li key={broadcast.uuid}>
                    <Card className="flex flex-col gap-1.5">
                      <p className="whitespace-pre-wrap break-words text-footnote text-fg">{broadcast.text}</p>
                      <p className="flex flex-wrap items-center gap-2 text-caption text-fg-muted">
                        <Pill tone={broadcast.status === 'sent' ? 'success' : broadcast.status === 'failed' ? 'danger' : 'info'}>{t(STATUS_LABEL[broadcast.status] ?? 'stores_broadcastStatus_queued')}</Pill>
                        <span>{t('stores_broadcastRow', { recipients: broadcast.recipients, stores: broadcast.stores, name: broadcast.created_by?.name ?? '—' })}</span>
                        <span>{when(broadcast.created_at)}</span>
                      </p>
                    </Card>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>
    </>
  );
}

function Composer({ onSent }: { onSent: (count: number) => void }) {
  const t = useT();
  const preview = usePreviewBroadcast();
  const send = useSendBroadcast();
  const [audience, setAudience] = useState<Audience>('active');
  const [picked, setPicked] = useState<Array<{ id: number; name: string }>>([]);
  const [text, setText] = useState('');
  const [reason, setReason] = useState('');
  const [shown, setShown] = useState<BroadcastPreview | null>(null);
  const [changed, setChanged] = useState(false);

  const body = (): BroadcastAudience =>
    audience === 'selected' ? { audience: 'selected', store_ids: picked.map((store) => store.id) } : audience === 'active' ? { audience: 'all', statuses: ['active'] } : { audience: 'all' };
  const reset = () => {
    setShown(null);
    setChanged(false);
  };
  const look = () => preview.mutate(body(), { onSuccess: (result) => setShown(result) });
  const canPreview = audience !== 'selected' || picked.length > 0;
  const canSend = shown !== null && shown.recipients > 0 && text.trim() !== '' && reason.trim().length >= 3;

  return (
    <Card className="flex flex-col gap-3">
      <h2 className="text-body font-bold text-fg">{t('stores_broadcastTitle')}</h2>
      <fieldset className="flex flex-col gap-1">
        <legend className="mb-1 text-footnote font-bold text-fg">{t('stores_broadcastAudience')}</legend>
        {(
          [
            ['active', 'stores_broadcastActive'],
            ['all', 'stores_broadcastAll'],
            ['selected', 'stores_broadcastSelected'],
          ] as Array<[Audience, MessageKey]>
        ).map(([value, label]) => (
          <label key={value} className="flex min-h-11 items-center gap-2 text-footnote text-fg">
            <input
              type="radio"
              name="audience"
              className="size-5"
              checked={audience === value}
              onChange={() => {
                setAudience(value);
                reset();
              }}
            />
            {t(label)}
          </label>
        ))}
      </fieldset>
      {audience === 'selected' && (
        <div className="flex flex-col gap-2">
          <StorePicker
            value={null}
            label={t('stores_broadcastPick')}
            onChange={(id, name) => {
              if (id !== null && !picked.some((store) => store.id === id)) setPicked((list) => [...list, { id, name: name ?? `#${id}` }]);
              reset();
            }}
          />
          {picked.length > 0 && (
            <ul className="flex flex-wrap gap-2">
              {picked.map((store) => (
                <li key={store.id} className="inline-flex items-center gap-1 rounded-full border border-line bg-surface-2 pl-3 text-footnote text-fg">
                  {store.name}
                  <IconButton
                    icon={X}
                    label={t('stores_broadcastRemove', { name: store.name })}
                    onClick={() => {
                      setPicked((list) => list.filter((item) => item.id !== store.id));
                      reset();
                    }}
                  />
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
      <Field label={t('stores_broadcastText')} error={fieldError(send.error, 'text')}>
        {(control) => <textarea {...control} rows={4} maxLength={2000} className={fieldClass} value={text} onChange={(event) => setText(event.target.value)} />}
      </Field>
      <Field label={t('stores_reason')} hint={t('stores_reasonHint')} error={fieldError(send.error, 'reason')}>
        {(control) => <input {...control} type="text" maxLength={500} className={fieldClass} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" loading={preview.isPending} disabled={!canPreview} onClick={look}>
          {t('stores_broadcastPreview')}
        </Button>
      </div>
      <FormError message={preview.error ? errorText(preview.error, t) : null} />
      {shown && (
        <div className="flex flex-col gap-2 rounded-control border border-line bg-surface-2 p-3" aria-live="polite">
          {changed && <p className="text-footnote font-bold text-warning-soft-fg">{t('stores_broadcastChanged')}</p>}
          <p className="text-footnote text-fg">{t('stores_broadcastPreviewText', { stores: shown.stores, recipients: shown.recipients, without: shown.without_telegram })}</p>
          {shown.sample.length > 0 && <p className="text-caption text-fg-muted">{t('stores_broadcastSample', { names: shown.sample.join(', ') })}</p>}
          <div>
            <Button
              icon={Send}
              loading={send.isPending}
              disabled={!canSend}
              onClick={() =>
                send.mutate(
                  { ...body(), text: text.trim(), recipients: shown.recipients, reason: reason.trim() },
                  {
                    onSuccess: (result) => {
                      setText('');
                      setReason('');
                      reset();
                      onSent(result.recipients);
                    },
                    onError: (error) => {
                      // The audience changed since the preview: show the new count, the member sends again knowingly
                      if (error instanceof ApiError && error.code === 'count_changed') {
                        preview.mutate(body(), {
                          onSuccess: (result) => {
                            setShown(result);
                            setChanged(true);
                          },
                        });
                      }
                    },
                  },
                )
              }
            >
              {t('stores_broadcastSend', { count: shown.recipients })}
            </Button>
          </div>
          <FormError message={send.error && !(send.error instanceof ApiError && send.error.code === 'count_changed') && !fieldError(send.error, 'text') && !fieldError(send.error, 'reason') ? errorText(send.error, t) : null} />
        </div>
      )}
    </Card>
  );
}
