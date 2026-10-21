'use client';

import { useId, useState, type FormEvent } from 'react';
import { ExternalLink } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { Button, Dialog, Field, buttonClasses, fieldClass, formatMoney } from '@rizqim/ui';
import { getApiErrorMessage, getApiFieldError, reportKeys, useInitiateTopup, useRequestPayout, type TopupProvider } from '@rizqim/staff';
import { paymentLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';

// Only the digits of a typed card number, in groups of four ("8600 1234 ...")
const groupCard = (value: string) => value.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');

// Pul yechish: the person asks, the server decides (its rules and messages are shown as they come)
export function PayoutDialog({ open, onClose, minReserve }: { open: boolean; onClose: () => void; minReserve: number }) {
  const { t, language } = useI18n();
  const queryClient = useQueryClient();
  const formId = useId();
  const [amount, setAmount] = useState('');
  const [card, setCard] = useState('');
  const [notes, setNotes] = useState('');
  const [sent, setSent] = useState(false);

  const payout = useRequestPayout({
    onSuccess: () => {
      setSent(true);
      void queryClient.invalidateQueries({ queryKey: reportKeys.history });
    },
  });

  const close = () => {
    if (payout.isPending) return;
    payout.reset();
    setSent(false);
    setAmount('');
    setCard('');
    setNotes('');
    onClose();
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    payout.mutate({ amount: Number(amount), card_number: card.replace(/\s+/g, ''), notes: notes.trim() === '' ? null : notes.trim() });
  };

  const fieldError = (field: string) => getApiFieldError(payout.error, field);
  const generalError = payout.error && !fieldError('amount') && !fieldError('card_number') && !fieldError('notes') ? (getApiErrorMessage(payout.error) ?? t('unknownError')) : undefined;

  return (
    <Dialog
      open={open}
      onClose={close}
      busy={payout.isPending}
      closeLabel={t('fin_close')}
      title={t('fin_payoutTitle')}
      footer={
        sent ? (
          <Button onClick={close}>{t('fin_close')}</Button>
        ) : (
          <>
            <Button variant="secondary" onClick={close} disabled={payout.isPending}>
              {t('fin_close')}
            </Button>
            <Button type="submit" form={formId} loading={payout.isPending} disabled={amount.trim() === '' || card.replace(/\s+/g, '') === ''}>
              {t('fin_payoutSend')}
            </Button>
          </>
        )
      }
    >
      {sent ? (
        <p role="status" className="text-body text-success-soft-fg">
          {t('fin_payoutSent')}
        </p>
      ) : (
        <form id={formId} onSubmit={submit} className="flex flex-col gap-4" noValidate>
          <Field label={t('fin_amount')} hint={t('fin_amountHint', { reserve: formatMoney(minReserve, language) })} error={fieldError('amount')}>
            {(props) => <input {...props} data-autofocus type="number" inputMode="numeric" min={1000} step="any" className={fieldClass} value={amount} onChange={(event) => setAmount(event.target.value)} />}
          </Field>
          <Field label={t('fin_card')} hint={t('fin_cardHint')} error={fieldError('card_number')}>
            {(props) => <input {...props} type="text" inputMode="numeric" autoComplete="off" placeholder="8600 0000 0000 0000" className={fieldClass} value={card} onChange={(event) => setCard(groupCard(event.target.value))} />}
          </Field>
          <Field label={t('fin_notes')} error={fieldError('notes')}>
            {(props) => <textarea {...props} rows={2} maxLength={500} className={fieldClass} value={notes} onChange={(event) => setNotes(event.target.value)} />}
          </Field>
          {generalError && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {generalError}
            </p>
          )}
        </form>
      )}
    </Dialog>
  );
}

const PROVIDERS: readonly TopupProvider[] = ['click', 'payme'];

// Balansni to'ldirish: the server makes the payment page, the person pays there (a link, not a pop-up the browser may block)
export function TopupDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { t, language } = useI18n();
  const formId = useId();
  const [amount, setAmount] = useState('');
  const [provider, setProvider] = useState<TopupProvider>('click');
  const topup = useInitiateTopup();

  const close = () => {
    if (topup.isPending) return;
    topup.reset();
    setAmount('');
    onClose();
  };

  const url = topup.data?.payment_url;
  // Only a web address is offered as a link
  const safeUrl = url && /^https:\/\//i.test(url) ? url : undefined;
  const fieldError = getApiFieldError(topup.error, 'amount');
  const generalError = topup.error && !fieldError ? (getApiErrorMessage(topup.error) ?? t('unknownError')) : topup.isSuccess && !safeUrl ? t('unknownError') : undefined;

  return (
    <Dialog
      open={open}
      onClose={close}
      busy={topup.isPending}
      closeLabel={t('fin_close')}
      title={t('fin_topupTitle')}
      footer={
        safeUrl ? (
          <>
            <Button variant="secondary" onClick={close}>
              {t('fin_close')}
            </Button>
            <a className={buttonClasses()} href={safeUrl} target="_blank" rel="noopener noreferrer">
              <ExternalLink aria-hidden="true" className="size-[18px]" />
              {t('fin_topupOpen')}
            </a>
          </>
        ) : (
          <>
            <Button variant="secondary" onClick={close} disabled={topup.isPending}>
              {t('fin_close')}
            </Button>
            <Button type="submit" form={formId} loading={topup.isPending} disabled={amount.trim() === ''}>
              {t('fin_topupGo')}
            </Button>
          </>
        )
      }
    >
      {safeUrl ? (
        <p role="status" className="text-body text-success-soft-fg">
          {t('fin_topupReady')}
        </p>
      ) : (
        <form
          id={formId}
          noValidate
          className="flex flex-col gap-4"
          onSubmit={(event) => {
            event.preventDefault();
            topup.mutate({ amount: Number(amount), provider });
          }}
        >
          <Field label={t('fin_amount')} error={fieldError}>
            {(props) => <input {...props} data-autofocus type="number" inputMode="numeric" min={1000} step="any" className={fieldClass} value={amount} onChange={(event) => setAmount(event.target.value)} />}
          </Field>
          <Field label={t('fin_provider')}>
            {(props) => (
              <select {...props} className={fieldClass} value={provider} onChange={(event) => setProvider(event.target.value as TopupProvider)}>
                {PROVIDERS.map((option) => (
                  <option key={option} value={option}>
                    {paymentLabel(language, option)}
                  </option>
                ))}
              </select>
            )}
          </Field>
          {generalError && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {generalError}
            </p>
          )}
        </form>
      )}
    </Dialog>
  );
}
