import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Banknote, CheckCircle2, Clock, CreditCard, Gauge, Send, ShieldAlert, TrendingDown, TrendingUp, XCircle } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { Button, Card, Dialog, InlineError, PageHeader, Section, cx } from '../components/ui';
import { Field } from '../components/staff/Field';
import { fieldClass } from '../components/staff/fieldStyles';
import { Pill, type PillTone } from '../components/staff/Pill';
import { Skeleton } from '../components/staff/Skeleton';
import { StoreStatusPill } from '../components/staff/StoreStatusPill';
import api from '../services/api';
import { tg, hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { getApiErrorMessage, getErrorMessage } from '../lib/apiError';
import { parseAmount, sumAmounts, toAmount } from '../lib/money';
import { formatDate, formatDateTime, formatMoney } from '../lib/format';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import type { Amount, ApiResponse, OwnerFinance, PayoutStatus, TopupInitiated } from '../types';

// What the API falls back to when a store has no value of its own.
const DEFAULT_MIN_PAYOUT_RESERVE = 100_000;
const DEFAULT_COMMISSION_RATE = 10;
// POST /owner/finance/top-up takes at least this (validation: min:1000).
const MIN_TOPUP = 1000;
const TOPUP_PRESETS = [50_000, 100_000, 250_000, 500_000];

const errorText = (err: unknown) => getApiErrorMessage(err) || getErrorMessage(err) || '';

// Money arrives as floats and decimal strings alike: lib/format reads it through toAmount.
const formatPrice = (price: Amount) => formatMoney(price);

const payoutTone = (status: PayoutStatus): PillTone => (status === 'completed' ? 'success' : status === 'rejected' ? 'danger' : 'warning');

export default function OwnerPayments() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const queryClient = useQueryClient();

  // State for Payout form
  const [amount, setAmount] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [notes, setNotes] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  // State for Topup form
  const [showTopupModal, setShowTopupModal] = useState(false);
  const [topupAmount, setTopupAmount] = useState('100000');
  const [topupProvider, setTopupProvider] = useState<'click' | 'payme'>('click');

  // Mutation to initiate top-up
  const topupMutation = useMutation({
    mutationFn: async (payload: { amount: number; provider: string }) => {
      const res = await api.post<ApiResponse<TopupInitiated>>('/owner/finance/top-up', payload);
      return res.data?.data;
    },
    onSuccess: (data) => {
      notificationOccurred('success');
      setShowTopupModal(false);
      const paymentUrl = data?.payment_url;
      if (paymentUrl) {
        if (tg.openLink && typeof tg.openLink === 'function') {
          tg.openLink(paymentUrl);
        } else {
          window.open(paymentUrl, '_blank');
        }
      }
    },
    onError: (err: unknown) => {
      notificationOccurred('error');
      const msg = errorText(err);
      void showAlert(uz ? `Xatolik: ${msg}` : `Ошибка: ${msg}`);
    }
  });

  // 1. Fetch finance details. The balances move whenever an order is commissioned or a top-up lands, so the
  // screen refreshes itself while it is visible (never in the background, backing off while the server fails).
  const { data: financeData, isLoading, refetch, isRefetching } = useQuery({
    queryKey: ['ownerFinance'],
    queryFn: async () => {
      const res = await api.get<ApiResponse<OwnerFinance>>('/owner/finance');
      return res.data?.data || null;
    },
    ...pollingQueryOptions(POLL_INTERVAL_MS.ownerFinance),
  });

  // 2. Mutation to request payout
  const payoutMutation = useMutation({
    mutationFn: async (payload: { amount: number; card_number: string; notes?: string }) => {
      const res = await api.post('/owner/payouts', payload);
      return res.data;
    },
    onSuccess: () => {
      notificationOccurred('success');
      setSuccessMessage(
        uz
          ? 'Yechib olish so\'rovi muvaffaqiyatli yuborildi!'
          : 'Запрос на выплату успешно отправлен!'
      );
      setAmount('');
      setCardNumber('');
      setNotes('');
      setErrorMessage('');
      queryClient.invalidateQueries({ queryKey: ['ownerFinance'] });
      setTimeout(() => setSuccessMessage(''), 5000);
    },
    onError: (err: unknown) => {
      notificationOccurred('error');
      setErrorMessage(errorText(err));
      setSuccessMessage('');
    },
  });

  const title = uz ? 'Balans va moliya' : 'Баланс и финансы';
  const subtitle = uz ? 'Platforma bilan hisob-kitoblar' : 'Взаиморасчеты с платформой';

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2" role="status" aria-label={uz ? 'Moliya yuklanmoqda' : 'Загрузка'}>
        <PageHeader title={title} subtitle={subtitle} />
        <div className="grid grid-cols-2 gap-3">
          <Skeleton className="h-24 rounded-card" />
          <Skeleton className="h-24 rounded-card" />
          <Skeleton className="col-span-2 h-28 rounded-card" />
        </div>
        <Skeleton className="h-14 rounded-card" />
      </div>
    );
  }
  // Only a first load that failed leaves nothing to show; a poll that fails later keeps the last balances on screen.
  if (!financeData) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={title} subtitle={subtitle} />
        <InlineError title="Moliya ma'lumotlarini yuklashda xatolik yuz berdi." onRetry={() => void refetch()} retrying={isRefetching} />
      </div>
    );
  }

  const {
    payment_account_type = 'own_account',
    status = 'active',
    ledger_transactions = [],
    payout_requests = [],
  } = financeData;
  const debtBalance = toAmount(financeData.debt_balance);
  const walletBalance = toAmount(financeData.wallet_balance);
  const creditLimit = toAmount(financeData.credit_limit);
  const minPayoutReserve = parseAmount(financeData.min_payout_reserve) ?? DEFAULT_MIN_PAYOUT_RESERVE;
  const commissionRate = parseAmount(financeData.commission_rate) ?? DEFAULT_COMMISSION_RATE;

  const hasPlatformWallet = payment_account_type === 'platform_account';
  // What the top-up field holds as a number; null while it is empty or not a number
  const topupValue = parseAmount(topupAmount);

  // Under Aggregator model:
  // - debtBalance > 0 => Cafe owes Platform (Debt)
  // - debtBalance < 0 => Cafe has prepaid (Advance)
  const currentDebt = debtBalance > 0 ? debtBalance : 0;
  const advancePayment = debtBalance < 0 ? Math.abs(debtBalance) : 0;
  const availableCredit = Math.max(0, sumAmounts([Math.abs(creditLimit), -debtBalance]));

  const handlePayoutSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const payoutAmount = parseAmount(amount);
    if (payoutAmount === null || payoutAmount <= 0) {
      setErrorMessage(uz ? 'To\'g\'ri summani kiriting' : 'Введите корректную сумму');
      return;
    }
    const minReserve = minPayoutReserve;
    if (walletBalance < minReserve) {
      setErrorMessage(
        uz
          ? `Mablag' yechish uchun hamyonda kamida ${formatPrice(minReserve)} qoldiq bo'lishi shart`
          : `Для вывода средств на балансе кошелька должно быть не менее ${formatPrice(minReserve)}`
      );
      return;
    }
    if (sumAmounts([walletBalance, -payoutAmount]) < minReserve) {
      const maxPayout = sumAmounts([walletBalance, -minReserve]);
      setErrorMessage(
        uz
          ? `Yechib olingandan so'ng hamyonda kamida ${formatPrice(minReserve)} qolishi shart. Maksimal yechish summasi: ${formatPrice(maxPayout)}`
          : `После вывода на балансе должно остаться не менее ${formatPrice(minReserve)}. Макс. сумма вывода: ${formatPrice(maxPayout)}`
      );
      return;
    }
    if (!cardNumber || cardNumber.replace(/\s/g, '').length < 16) {
      setErrorMessage(
        uz ? 'Karta raqamini to\'liq kiriting' : 'Введите полный номер карты'
      );
      return;
    }

    payoutMutation.mutate({
      amount: payoutAmount,
      card_number: cardNumber.replace(/\s/g, ''),
      notes: notes || undefined,
    });
  };

  const formatCardNumber = (value: string) => {
    const v = value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    const matches = v.match(/\d{4,16}/g);
    const match = (matches && matches[0]) || '';
    const parts = [];

    for (let i = 0, len = match.length; i < len; i += 4) {
      parts.push(match.substring(i, i + 4));
    }

    if (parts.length > 0) {
      return parts.join(' ');
    } else {
      return v;
    }
  };

  const payoutStatus = (reqStatus: PayoutStatus) => {
    if (reqStatus === 'completed') return { label: uz ? 'Bajarildi' : 'Выполнено', icon: CheckCircle2 };
    if (reqStatus === 'rejected') return { label: uz ? 'Rad etildi' : 'Отклонено', icon: XCircle };
    return { label: uz ? 'Kutilmoqda' : 'В ожидании', icon: Clock };
  };

  const topupDisabled = topupValue === null || topupValue < MIN_TOPUP;

  return (
    <div className="flex flex-col gap-5 px-4 pt-2 animate-fade-in">
      <PageHeader title={title} subtitle={subtitle} action={<StoreStatusPill status={status} />} />

      {/* Main stats */}
      <div className="grid grid-cols-2 gap-3">
        <Card className="flex flex-col gap-1 p-3">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <ShieldAlert aria-hidden="true" className="size-4 shrink-0 text-danger" />
            {uz ? 'Komissiya qarzi' : 'Долг по комиссии'}
          </span>
          <span className="text-headline font-bold text-fg">{formatPrice(currentDebt)}</span>
          <span className="text-caption text-fg-muted">
            {debtBalance > 0 ? (uz ? 'To\'lanishi kerak' : 'Необходимо оплатить') : (uz ? 'Qarz yo\'q' : 'Долга нет')}
          </span>
        </Card>

        <Card className="flex flex-col gap-1 p-3">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <Banknote aria-hidden="true" className="size-4 shrink-0 text-success" />
            {uz ? 'Oldindan to\'lov' : 'Предоплата'}
          </span>
          <span className="text-headline font-bold text-fg">{formatPrice(advancePayment)}</span>
          <span className="text-caption text-fg-muted">{uz ? 'Balansdagi depozit' : 'Депозит на балансе'}</span>
        </Card>

        <Card className="col-span-2 flex items-center justify-between gap-3">
          <div className="flex min-w-0 flex-col gap-1">
            <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
              <Gauge aria-hidden="true" className="size-4 shrink-0 text-brand" />
              {uz ? 'Mavjud limit (kredit)' : 'Доступный лимит (кредит)'}
            </span>
            <span className="text-title font-bold text-fg">{formatPrice(availableCredit)}</span>
            <span className="text-caption text-fg-muted">
              {uz ? `Maksimal qarz limiti: ${formatPrice(Math.abs(creditLimit))}` : `Максимальный лимит долга: ${formatPrice(Math.abs(creditLimit))}`}
            </span>
          </div>
          <Pill tone="brand" className="self-start">
            {commissionRate}% {uz ? 'komissiya' : 'комиссия'}
          </Pill>
        </Card>
      </div>

      {/* Top up */}
      <Button
        size="lg"
        icon={CreditCard}
        onClick={() => { hapticFeedback('light'); setShowTopupModal(true); }}
        fullWidth
      >
        {uz ? "Balansni to'ldirish" : 'Пополнить баланс'}
      </Button>

      {/* Wallet balance (platform payments mode) */}
      {hasPlatformWallet && (
        <Card className="flex flex-col gap-1">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <Banknote aria-hidden="true" className="size-4 shrink-0 text-success" />
            {uz ? 'Hamyon balansi (onlayn tushumlar)' : 'Баланс кошелька (онлайн оплаты)'}
          </span>
          <span className="text-title font-bold text-success-soft-fg">{formatPrice(walletBalance)}</span>
          <span className="text-caption text-fg-muted">{uz ? 'Yechib olish mumkin bo\'lgan summa' : 'Сумма, доступная к выводу'}</span>
        </Card>
      )}

      {/* Warning box */}
      {debtBalance > Math.abs(creditLimit) && (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-danger-soft p-4 text-danger-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <div className="flex flex-col gap-1">
            <h2 className="text-body font-bold">{uz ? 'Qarz limiti tugadi!' : 'Лимит долга исчерпан!'}</h2>
            <p className="text-footnote">
              {uz
                ? 'Qarzdorligingiz ruxsat etilgan limitdan oshib ketdi. Yangi buyurtmalar qabul qilish to\'xtatildi. Iltimos, balansni to\'ldiring!'
                : 'Ваш долг превысил допустимый лимит. Прием новых заказов приостановлен. Пожалуйста, пополните баланс!'}
            </p>
          </div>
        </div>
      )}

      {/* Withdraw / payout form (only with a platform wallet that holds money) */}
      {hasPlatformWallet && walletBalance > 0 && (
        <Section title={uz ? 'Mablag\'ni yechib olish' : 'Вывод средств'}>
          <Card>
            <form onSubmit={handlePayoutSubmit} className="flex flex-col gap-4">
              <p className="text-footnote text-fg-muted">
                {uz ? 'Platformadan kartangizga pul o\'tkazish so\'rovi' : 'Запрос на перевод денег с платформы на карту'}
              </p>

              <Field
                label={uz ? 'Summa (UZS)' : 'Сумма (UZS)'}
                hint={uz ? `Min. qoldiq: ${formatPrice(minPayoutReserve)}` : `Мин. остаток: ${formatPrice(minPayoutReserve)}`}
              >
                {(props) => (
                  <input
                    {...props}
                    type="number"
                    inputMode="numeric"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    placeholder="Masalan: 50000"
                    className={fieldClass}
                  />
                )}
              </Field>

              <Field label={uz ? 'Karta raqami (8600... / 9860...)' : 'Номер карты (8600... / 9860...)'}>
                {(props) => (
                  <input
                    {...props}
                    type="text"
                    inputMode="numeric"
                    value={cardNumber}
                    onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                    placeholder="0000 0000 0000 0000"
                    maxLength={19}
                    className={fieldClass}
                  />
                )}
              </Field>

              <Field label={uz ? 'Izoh (ixtiyoriy)' : 'Примечание (необязательно)'}>
                {(props) => (
                  <input
                    {...props}
                    type="text"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder={uz ? 'Qo\'shimcha izohlar...' : 'Дополнительные примечания...'}
                    className={fieldClass}
                  />
                )}
              </Field>

              {successMessage && (
                <p role="status" className="rounded-control bg-success-soft px-4 py-2.5 text-footnote font-bold text-success-soft-fg">
                  {successMessage}
                </p>
              )}

              {errorMessage && (
                <p role="alert" className="rounded-control bg-danger-soft px-4 py-2.5 text-footnote font-bold text-danger-soft-fg">
                  {errorMessage}
                </p>
              )}

              <Button type="submit" icon={Send} loading={payoutMutation.isPending} fullWidth>
                {payoutMutation.isPending
                  ? (uz ? 'Yuborilmoqda...' : 'Отправка...')
                  : (uz ? 'Yechib olishni so\'rash' : 'Запросить выплату')}
              </Button>
            </form>
          </Card>
        </Section>
      )}

      {/* Payout history */}
      {hasPlatformWallet && payout_requests.length > 0 && (
        <Section title={uz ? 'Yechib olish so\'rovlari' : 'Запросы на выплату'}>
          <ul className="flex flex-col gap-2">
            {payout_requests.map((req) => {
              const state = payoutStatus(req.status);
              return (
                <li key={req.id}>
                  <Card className="flex items-center justify-between gap-3 p-3">
                    <div className="min-w-0">
                      <span className="block text-body font-bold text-fg">{formatPrice(req.amount)}</span>
                      <span className="block text-footnote text-fg-muted">
                        Karta: {req.card_number.replace(/(\d{4})\d{8}(\d{4})/, '$1 **** **** $2')}
                      </span>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1">
                      <Pill tone={payoutTone(req.status)} icon={state.icon}>
                        {state.label}
                      </Pill>
                      <span className="text-caption text-fg-muted">{formatDate(req.created_at)}</span>
                    </div>
                  </Card>
                </li>
              );
            })}
          </ul>
        </Section>
      )}

      {/* Ledger (debt / top-up log) */}
      <Section title={uz ? 'Hisob-kitoblar tarixi' : 'История взаиморасчетов'}>
        {ledger_transactions.length === 0 ? (
          <Card className="text-center">
            <p className="text-footnote text-fg-muted">
              {uz ? 'Hozircha moliyaviy tranzaksiyalar yo\'q.' : 'Финансовых транзакций пока нет.'}
            </p>
          </Card>
        ) : (
          <ul className="flex flex-col gap-2">
            {ledger_transactions.map((tx) => {
              const amountValue = toAmount(tx.amount);
              // In this ledger:
              // - positive amount (e.g. +15,000 UZS) represents commission deduction (owes us more)
              // - negative amount (e.g. -100,000 UZS) represents store topup/payment (owes us less)
              const isCommission = tx.type === 'commission' || amountValue > 0;
              const isRefund = tx.type === 'refund';
              const label = isRefund
                ? (uz ? 'Buyurtma qaytarildi' : 'Возврат заказа')
                : isCommission
                  ? (uz ? 'Buyurtma komissiyasi' : 'Комиссия с заказа')
                  : (uz ? 'Balansni to\'ldirish' : 'Пополнение баланса');

              return (
                <li key={tx.id}>
                  <Card className="flex items-center justify-between gap-3 p-3">
                    <div className="flex min-w-0 items-center gap-3">
                      <span
                        className={cx(
                          'flex size-10 shrink-0 items-center justify-center rounded-control',
                          isCommission ? 'bg-danger-soft text-danger-soft-fg' : 'bg-success-soft text-success-soft-fg',
                        )}
                      >
                        {isCommission ? <TrendingUp aria-hidden="true" className="size-5" /> : <TrendingDown aria-hidden="true" className="size-5" />}
                      </span>
                      <div className="min-w-0">
                        <span className="block text-body font-medium text-fg">{label}</span>
                        <span className="block text-footnote text-fg-muted">{formatDateTime(tx.created_at)}</span>
                        <span className="block text-caption text-fg-muted">
                          {uz ? 'Qarzdorlik: ' : 'Задолженность: '}{formatPrice(tx.balance_after)}
                        </span>
                      </div>
                    </div>
                    <span className={cx('shrink-0 text-body font-bold', isCommission ? 'text-danger-soft-fg' : 'text-success-soft-fg')}>
                      {isCommission ? '+' : '-'}{formatPrice(Math.abs(amountValue))}
                    </span>
                  </Card>
                </li>
              );
            })}
          </ul>
        )}
      </Section>

      {/* Top-up sheet */}
      <Dialog
        open={showTopupModal}
        onClose={() => { hapticFeedback('light'); setShowTopupModal(false); }}
        busy={topupMutation.isPending}
        title={uz ? "Balansni to'ldirish" : 'Пополнение баланса'}
        footer={
          <Button
            size="lg"
            loading={topupMutation.isPending}
            disabled={topupDisabled}
            onClick={() => {
              if (topupValue === null || topupValue < MIN_TOPUP) return;
              hapticFeedback('medium');
              topupMutation.mutate({
                amount: topupValue,
                provider: topupProvider,
              });
            }}
          >
            {uz ? "To'lashga o'tish" : 'Перейти к оплате'}
          </Button>
        }
      >
        <div className="flex flex-col gap-4">
          <Field
            label={uz ? "To'lov summasi (UZS)" : 'Сумма оплаты (UZS)'}
            hint={uz ? `Kamida ${formatPrice(MIN_TOPUP)}` : `Минимум ${formatPrice(MIN_TOPUP)}`}
          >
            {(props) => (
              <input
                {...props}
                type="number"
                inputMode="numeric"
                value={topupAmount}
                onChange={(e) => setTopupAmount(e.target.value)}
                placeholder="Masalan: 50000"
                className={fieldClass}
              />
            )}
          </Field>

          {/* Quick choices */}
          <div role="group" aria-label={uz ? 'Tez tanlash' : 'Быстрый выбор'} className="grid grid-cols-4 gap-2">
            {TOPUP_PRESETS.map((preset) => (
              <button
                key={preset}
                type="button"
                aria-pressed={topupValue === preset}
                onClick={() => { selectionChanged(); setTopupAmount(String(preset)); }}
                className={cx(
                  'min-h-11 cursor-pointer rounded-control border text-body font-bold transition-colors',
                  'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                  topupValue === preset ? 'border-brand bg-brand-soft text-brand-soft-fg' : 'border-line bg-surface-2 text-fg',
                )}
              >
                {(preset / 1000) + 'k'}
              </button>
            ))}
          </div>

          {/* Payment provider */}
          <div className="flex flex-col gap-1.5">
            <span id="topup-provider" className="text-footnote font-bold text-fg-muted">{uz ? "To'lov tizimi" : 'Платежная система'}</span>
            <div role="group" aria-labelledby="topup-provider" className="grid grid-cols-2 gap-2">
              {([['click', 'Click'], ['payme', 'Payme']] as const).map(([provider, name]) => (
                <button
                  key={provider}
                  type="button"
                  aria-pressed={topupProvider === provider}
                  onClick={() => { selectionChanged(); setTopupProvider(provider); }}
                  className={cx(
                    'min-h-12 cursor-pointer rounded-card border-2 text-headline font-bold transition-colors',
                    'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                    topupProvider === provider ? 'border-brand bg-brand-soft text-brand-soft-fg' : 'border-line bg-surface-2 text-fg',
                  )}
                >
                  {name}
                </button>
              ))}
            </div>
          </div>
        </div>
      </Dialog>
    </div>
  );
}
