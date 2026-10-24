'use client';

import { useState } from 'react';
import { Copy, Send } from 'lucide-react';
import { Button, InlineError, Skeleton, formatMoney } from '@rizqim/ui';
import { useBookingConfirmation, useSendBookingConfirmation, type BookingDetail } from '@rizqim/staff';
import { inZone } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { errorText, reasonOf } from '../management/errors';

// "Tasdiq" (P3-38): the message the guest gets when the booking is confirmed, as the server builds it from the room's tariff for
// that date, with "Yuborish" (Telegram if the guest has a chat with the store's bot, else SMS if the branch switched it on) and
// "Nusxa olish" for a cashier who sends it from their own messenger. Read only on demand: the text is built by the server.
export function BookingConfirmationSection({ booking, timeZone }: { booking: BookingDetail; timeZone: string }) {
  const { t, language } = useI18n();
  const [open, setOpen] = useState(false);
  const [result, setResult] = useState<{ tone: 'ok' | 'bad'; text: string } | null>(null);
  const preview = useBookingConfirmation(booking.uuid, { enabled: open });
  const send = useSendBookingConfirmation();

  const channelName = (channel: string | null | undefined) => (channel === 'telegram' ? t('book_confChanTelegram') : channel === 'sms' ? t('book_confChanSms') : (channel ?? ''));
  const sentAt = booking.confirmation_sent_at ? inZone(booking.confirmation_sent_at, timeZone) : null;

  const doSend = () => {
    setResult(null);
    send.mutate(
      { uuid: booking.uuid },
      {
        onSuccess: (sent) => setResult({ tone: 'ok', text: sent.channel === 'telegram' ? t('book_confSentTelegram') : sent.channel === 'sms' ? t('book_confSentSms') : t('book_confSentOther', { channel: channelName(sent.channel) }) }),
        // 422 `no_channel`: nowhere to send it; said in words the cashier can act on
        onError: (error) => setResult({ tone: 'bad', text: reasonOf(error) === 'no_channel' ? t('book_confNoChannel') : errorText(error, t) }),
      },
    );
  };

  const copy = async () => {
    if (!preview.data) return;
    try {
      await navigator.clipboard.writeText(preview.data.text);
      setResult({ tone: 'ok', text: t('book_confCopied') });
    } catch {
      // The clipboard can be blocked; the text stays on the screen to select by hand
    }
  };

  const channels = preview.data ? [preview.data.channels.telegram ? t('book_confChanTelegram') : null, preview.data.channels.sms ? t('book_confChanSms') : null].filter((name): name is string => name !== null) : [];
  const fee = preview.data?.room_fee;

  return (
    <section aria-label={t('book_conf')} className="flex flex-col gap-2 rounded-card border border-line p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-body font-bold text-fg">{t('book_conf')}</h3>
        <Button variant="secondary" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
          {open ? t('book_confHide') : t('book_confShow')}
        </Button>
      </div>
      {sentAt && <p className="text-footnote text-fg-muted">{t('book_confSent', { time: sentAt.time, channel: channelName(booking.confirmation_channel) })}</p>}
      {result && (
        <p role={result.tone === 'bad' ? 'alert' : 'status'} className={result.tone === 'bad' ? 'rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg' : 'rounded-control bg-success-soft px-3 py-2.5 text-footnote font-bold text-success-soft-fg'}>
          {result.text}
        </p>
      )}
      {open &&
        (preview.isError ? (
          <InlineError title={t('book_confLoadError')} message={errorText(preview.error, t)} retryLabel={t('retry')} retrying={preview.isFetching} onRetry={() => void preview.refetch()} />
        ) : !preview.data ? (
          <Skeleton className="h-32 w-full" />
        ) : (
          <>
            <p className="text-caption text-fg-muted">{t('book_confHint')}</p>
            <pre aria-label={t('book_conf')} className="whitespace-pre-wrap break-words rounded-control bg-surface-2 p-3 font-sans text-body text-fg">
              {preview.data.text}
            </pre>
            {fee && <p className="text-footnote text-fg-muted">{t('book_confRoomFee', { amount: formatMoney(fee.amount, language) })}</p>}
            <p className="text-footnote text-fg-muted">{t('book_confChannels', { channels: channels.length > 0 ? channels.join(', ') : t('book_confChanNone') })}</p>
            <div className="flex flex-wrap gap-3">
              <Button icon={Send} loading={send.isPending} disabled={send.isPending} onClick={doSend}>
                {t('book_confSend')}
              </Button>
              <Button variant="secondary" icon={Copy} onClick={() => void copy()}>
                {t('book_confCopy')}
              </Button>
            </div>
          </>
        ))}
    </section>
  );
}
