import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { DollarSign, ShieldAlert, Activity, TrendingDown, TrendingUp, Send, CheckCircle2, XCircle, Clock } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { FullScreenLoader } from '../App';
import api from '../services/api';
import { tg, hapticFeedback } from '../lib/telegram';

export default function OwnerPayments() {
  const { language } = useLanguageStore();
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
      const res = await api.post('/owner/finance/top-up', payload);
      return res.data?.data;
    },
    onSuccess: (data) => {
      hapticFeedback('success');
      setShowTopupModal(false);
      const paymentUrl = data.payment_url;
      if (paymentUrl) {
        if (tg.openLink && typeof tg.openLink === 'function') {
          tg.openLink(paymentUrl);
        } else {
          window.open(paymentUrl, '_blank');
        }
      }
    },
    onError: (err: any) => {
      hapticFeedback('error');
      const msg = err.response?.data?.message || err.message;
      alert(language === 'uz' ? `Xatolik: ${msg}` : `Ошибка: ${msg}`);
    }
  });

  // 1. Fetch finance details
  const { data: financeData, isLoading, error } = useQuery({
    queryKey: ['ownerFinance'],
    queryFn: async () => {
      const res = await api.get('/owner/finance');
      return res.data?.data || null;
    },
    refetchInterval: 10000,
  });

  // 2. Mutation to request payout
  const payoutMutation = useMutation({
    mutationFn: async (payload: { amount: number; card_number: string; notes?: string }) => {
      const res = await api.post('/owner/payouts', payload);
      return res.data;
    },
    onSuccess: () => {
      setSuccessMessage(
        language === 'uz'
          ? 'Payout so\'rovi muvaffaqiyatli yuborildi!'
          : 'Запрос на выплату успешно отправлен!'
      );
      setAmount('');
      setCardNumber('');
      setNotes('');
      setErrorMessage('');
      queryClient.invalidateQueries({ queryKey: ['ownerFinance'] });
      setTimeout(() => setSuccessMessage(''), 5000);
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message;
      setErrorMessage(msg);
      setSuccessMessage('');
    },
  });

  if (isLoading) return <FullScreenLoader message="Moliya ma'lumotlari yuklanmoqda..." />;
  if (error || !financeData) return <div className="p-4 text-center text-red-500">Moliya ma'lumotlarini yuklashda xatolik yuz berdi.</div>;

  const {
    balance = 0,
    wallet_balance = 0,
    credit_limit = 0,
    min_payout_reserve = 100000,
    payment_account_type = 'own_account',
    commission_rate = 10,
    status = 'active',
    ledger_transactions = [],
    payout_requests = [],
  } = financeData;

  const hasPlatformWallet = payment_account_type === 'platform_account';

  // Under Aggregator model:
  // - balance > 0 => Cafe owes Platform (Debt)
  // - balance < 0 => Cafe has prepaid (Advance)
  const currentDebt = balance > 0 ? balance : 0;
  const advancePayment = balance < 0 ? Math.abs(balance) : 0;
  const availableCredit = Math.max(0, Math.abs(credit_limit) - balance);

  const formatPrice = (price: number) => {
    return price.toLocaleString() + ' UZS';
  };

  const getStatusBadge = (storeStatus: string) => {
    switch (storeStatus) {
      case 'active':
        return (
          <span className="bg-emerald-500/10 text-emerald-500 text-[10px] font-black px-3 py-1 rounded-full border border-emerald-500/20 uppercase tracking-wider">
            {language === 'uz' ? 'Faol' : 'Активен'}
          </span>
        );
      case 'payment_required':
        return (
          <span className="bg-rose-500/10 text-rose-500 text-[10px] font-black px-3 py-1 rounded-full border border-rose-500/20 uppercase tracking-wider animate-pulse">
            {language === 'uz' ? 'To\'lov kutilmoqda' : 'Требуется оплата'}
          </span>
        );
      case 'suspended':
        return (
          <span className="bg-amber-500/10 text-amber-500 text-[10px] font-black px-3 py-1 rounded-full border border-amber-500/20 uppercase tracking-wider">
            {language === 'uz' ? 'Muzlatilgan' : 'Приостановлен'}
          </span>
        );
      default:
        return (
          <span className="bg-gray-500/10 text-gray-500 text-[10px] font-black px-3 py-1 rounded-full border border-gray-500/20 uppercase tracking-wider">
            {storeStatus}
          </span>
        );
    }
  };

  const handlePayoutSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const amtVal = parseFloat(amount);
    if (!amount || amtVal <= 0) {
      setErrorMessage(language === 'uz' ? 'To\'g\'ri summani kiriting' : 'Введите корректную сумму');
      return;
    }
    const minReserve = min_payout_reserve;
    if (wallet_balance < minReserve) {
      setErrorMessage(
        language === 'uz'
          ? `Mablag' yechish uchun hamyonda kamida ${formatPrice(minReserve)} qoldiq bo'lishi shart`
          : `Для вывода средств на балансе кошелька должно быть не менее ${formatPrice(minReserve)}`
      );
      return;
    }
    if ((wallet_balance - amtVal) < minReserve) {
      setErrorMessage(
        language === 'uz'
          ? `Yechib olingandan so'ng hamyonda kamida ${formatPrice(minReserve)} qolishi shart. Maksimal yechish summasi: ${formatPrice(wallet_balance - minReserve)}`
          : `После вывода на балансе должно остаться не менее ${formatPrice(minReserve)}. Макс. сумма вывода: ${formatPrice(wallet_balance - minReserve)}`
      );
      return;
    }
    if (!cardNumber || cardNumber.replace(/\s/g, '').length < 16) {
      setErrorMessage(
        language === 'uz' ? 'Karta raqamini to\'liq kiriting' : 'Введите полный номер карты'
      );
      return;
    }

    payoutMutation.mutate({
      amount: parseFloat(amount),
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

  const getPayoutStatusIcon = (reqStatus: string) => {
    switch (reqStatus) {
      case 'completed':
        return <CheckCircle2 className="w-4 h-4 text-emerald-500" />;
      case 'rejected':
        return <XCircle className="w-4 h-4 text-rose-500" />;
      default:
        return <Clock className="w-4 h-4 text-amber-500 animate-pulse" />;
    }
  };

  const getPayoutStatusLabel = (reqStatus: string) => {
    if (reqStatus === 'completed') {
      return language === 'uz' ? 'Bajarildi' : 'Выполнено';
    }
    if (reqStatus === 'rejected') {
      return language === 'uz' ? 'Rad etildi' : 'Отклонено';
    }
    return language === 'uz' ? 'Kutilmoqda' : 'В ожидании';
  };

  return (
    <div className="p-4 pb-24 min-h-screen bg-gradient-to-br from-bg via-bg to-primary/5 space-y-6 animate-fade-in">
      {/* Header */}
      <div className="pt-2 flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-black text-text-h">
            {language === 'uz' ? 'Balans va Moliya' : 'Баланс и Финансы'}
          </h1>
          <p className="text-xs text-hint font-medium mt-1">
            {language === 'uz' ? 'Platforma bilan hisob-kitoblar' : 'Взаиморасчеты с платформой'}
          </p>
        </div>
        {getStatusBadge(status)}
      </div>

      {/* Main Stats Cards Grid */}
      <div className="grid grid-cols-2 gap-3.5">
        {/* Current Debt Card */}
        <div className="bg-secondary p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 shadow-sm relative overflow-hidden">
          <div className="absolute top-0 right-0 w-12 h-12 bg-red-500/5 rounded-full blur-lg"></div>
          <div className="flex items-center gap-1.5 text-red-500">
            <ShieldAlert className="w-4.5 h-4.5" />
            <span className="text-[9px] font-black uppercase tracking-wider text-hint">
              {language === 'uz' ? 'Komissiya Qarzi' : 'Долг по комиссии'}
            </span>
          </div>
          <span className="text-sm font-black text-text-h mt-1">
            {formatPrice(currentDebt)}
          </span>
          <span className="text-[8px] text-hint font-medium">
            {balance > 0 ? (language === 'uz' ? 'To\'lanishi kerak' : 'Необходимо оплатить') : (language === 'uz' ? 'Qarz yo\'q' : 'Долга нет')}
          </span>
        </div>

        {/* Prepaid/Advance Card */}
        <div className="bg-secondary p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 shadow-sm relative overflow-hidden">
          <div className="absolute top-0 right-0 w-12 h-12 bg-emerald-500/5 rounded-full blur-lg"></div>
          <div className="flex items-center gap-1.5 text-emerald-500">
            <DollarSign className="w-4.5 h-4.5" />
            <span className="text-[9px] font-black uppercase tracking-wider text-hint">
              {language === 'uz' ? 'Oldindan To\'lov' : 'Предоплата'}
            </span>
          </div>
          <span className="text-sm font-black text-text-h mt-1">
            {formatPrice(advancePayment)}
          </span>
          <span className="text-[8px] text-hint font-medium">
            {language === 'uz' ? 'Balansdagi depozit' : 'Депозит на балансе'}
          </span>
        </div>

        {/* Available Credit Card */}
        <div className="col-span-2 bg-secondary p-5 rounded-3xl border border-hint/5 flex justify-between items-center shadow-sm relative overflow-hidden">
          <div className="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full blur-xl pointer-events-none"></div>
          <div className="space-y-1">
            <div className="flex items-center gap-1.5 text-primary">
              <Activity className="w-4.5 h-4.5" />
              <span className="text-[9px] font-black uppercase tracking-wider text-hint">
                {language === 'uz' ? 'Mavjud Limit (Kredit)' : 'Доступный лимит (Кредит)'}
              </span>
            </div>
            <span className="text-2xl font-black text-text-h block">
              {formatPrice(availableCredit)}
            </span>
            <span className="text-[9px] text-hint font-medium block">
              {language === 'uz' ? `Maksimal qarz limiti: ${formatPrice(Math.abs(credit_limit))}` : `Максимальный лимит долга: ${formatPrice(Math.abs(credit_limit))}`}
            </span>
          </div>
          <div className="bg-primary/10 text-primary text-[10px] font-black px-2.5 py-1 rounded-xl border border-primary/20">
            {commission_rate}% {language === 'uz' ? 'komissiya' : 'комиссия'}
          </div>
        </div>
      </div>

      {/* Top Up Button */}
      <button
        onClick={() => { hapticFeedback('light'); setShowTopupModal(true); }}
        className="w-full bg-primary hover:bg-primary/95 text-white rounded-3xl py-3.5 text-xs font-black shadow-lg shadow-primary/15 flex items-center justify-center gap-1.5 transition-transform active:scale-95 cursor-pointer"
      >
        💳 {language === 'uz' ? "Balansni To'ldirish (Card Top Up)" : "Пополнение баланса (Картой)"}
      </button>

      {/* Wallet Balance Card (For Platform Payments mode) */}
      {hasPlatformWallet && (
        <div className="bg-secondary p-5 rounded-3xl border border-hint/5 flex justify-between items-center shadow-sm relative overflow-hidden">
          <div className="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl pointer-events-none"></div>
          <div className="space-y-1">
            <div className="flex items-center gap-1.5 text-emerald-500">
              <DollarSign className="w-4.5 h-4.5" />
              <span className="text-[9px] font-black uppercase tracking-wider text-hint">
                {language === 'uz' ? 'Hamyon Balansi (Onlayn tushumlar)' : 'Баланс кошелька (Онлайн оплаты)'}
              </span>
            </div>
            <span className="text-2xl font-black text-emerald-500 block">
              {formatPrice(wallet_balance)}
            </span>
            <span className="text-[9px] text-hint font-medium block">
              {language === 'uz' ? 'Yechib olish mumkin bo\'lgan summa' : 'Сумма, доступная к выводу'}
            </span>
          </div>
        </div>
      )}

      {/* Warning Box */}
      {balance >= Math.abs(credit_limit) && (
        <div className="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 flex gap-3 items-start animate-pulse">
          <span className="text-xl shrink-0">⚠️</span>
          <div>
            <h4 className="text-xs font-black text-rose-500 uppercase tracking-wider">
              {language === 'uz' ? 'Qarz Limiti Tugadi!' : 'Лимит долга исчерпан!'}
            </h4>
            <p className="text-[10px] text-rose-500/80 leading-normal mt-1 font-semibold">
              {language === 'uz'
                ? 'Qarzdorligingiz ruxsat etilgan limitdan oshib ketdi. Yangi buyurtmalar qabul qilish to\'xtatildi. Iltimos, balansni to\'ldiring!'
                : 'Ваш долг превысил допустимый лимит. Прием новых заказов приостановлен. Пожалуйста, пополните баланс!'}
            </p>
          </div>
        </div>
      )}

      {/* Withdraw / Payout Form (Visible only if hasPlatformWallet & wallet_balance > 0) */}
      {hasPlatformWallet && wallet_balance > 0 && (
        <div className="bg-secondary p-5 rounded-3xl border border-hint/5 space-y-4">
          <div>
            <h2 className="text-sm font-black text-text-h uppercase tracking-wider">
              {language === 'uz' ? 'Mablag\'ni Yechib Olish' : 'Вывод средств'}
            </h2>
            <p className="text-[10px] text-hint mt-0.5">
              {language === 'uz' ? 'Platformadan kartangizga pul o\'tkazish so\'rovi' : 'Запрос на перевод денег с платформы на карту'}
            </p>
          </div>

          <form onSubmit={handlePayoutSubmit} className="space-y-3">
            {/* Amount */}
            <div className="space-y-1">
              <div className="flex justify-between items-center pl-1">
                <label className="text-[10px] font-black uppercase text-hint tracking-wide">
                  {language === 'uz' ? 'Summa (UZS)' : 'Сумма (UZS)'}
                </label>
                <span className="text-[9px] text-hint font-bold text-amber-500">
                  {language === 'uz' ? `Min. qoldiq: ${formatPrice(min_payout_reserve)}` : `Мин. остаток: ${formatPrice(min_payout_reserve)}`}
                </span>
              </div>
              <input
                type="number"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="Masalan: 50000"
                className="w-full bg-bg border border-hint/10 focus:border-primary/40 focus:ring-0 rounded-2xl px-4 py-3 text-xs text-text-h font-black placeholder:font-medium placeholder:text-hint/40"
              />
            </div>

            {/* Card Number */}
            <div className="space-y-1">
              <label className="text-[10px] font-black uppercase text-hint tracking-wide pl-1">
                {language === 'uz' ? 'Karta raqami (8600... / 9860...)' : 'Номер карты (8600... / 9860...)'}
              </label>
              <input
                type="text"
                value={cardNumber}
                onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                placeholder="0000 0000 0000 0000"
                maxLength={19}
                className="w-full bg-bg border border-hint/10 focus:border-primary/40 focus:ring-0 rounded-2xl px-4 py-3 text-xs text-text-h font-black placeholder:font-medium placeholder:text-hint/40"
              />
            </div>

            {/* Notes */}
            <div className="space-y-1">
              <label className="text-[10px] font-black uppercase text-hint tracking-wide pl-1">
                {language === 'uz' ? 'Izoh (Ixtiyoriy)' : 'Примечание (Опционально)'}
              </label>
              <input
                type="text"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder={language === 'uz' ? 'Qo\'shimcha izohlar...' : 'Дополнительные примечания...'}
                className="w-full bg-bg border border-hint/10 focus:border-primary/40 focus:ring-0 rounded-2xl px-4 py-3 text-xs text-text-h font-black placeholder:font-medium placeholder:text-hint/40"
              />
            </div>

            {successMessage && (
              <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-[10px] font-black px-4 py-2.5 rounded-2xl">
                {successMessage}
              </div>
            )}

            {errorMessage && (
              <div className="bg-rose-500/10 border border-rose-500/20 text-rose-500 text-[10px] font-black px-4 py-2.5 rounded-2xl">
                {errorMessage}
              </div>
            )}

            <button
              type="submit"
              disabled={payoutMutation.isPending}
              className="w-full bg-primary hover:bg-primary/95 disabled:bg-primary/50 text-white rounded-2xl py-3 text-xs font-black shadow-sm shadow-primary/20 flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
            >
              <Send className="w-4 h-4" />
              {payoutMutation.isPending
                ? (language === 'uz' ? 'Yuborilmoqda...' : 'Отправка...')
                : (language === 'uz' ? 'Yechib olishni so\'rash' : 'Запросить выплату')}
            </button>
          </form>
        </div>
      )}

      {/* Payout History (Visible if hasPlatformWallet & requests exist) */}
      {hasPlatformWallet && payout_requests.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-black text-text-h uppercase tracking-wider pl-1">
            {language === 'uz' ? 'Yechib Olish So\'rovlari' : 'Запросы на выплату'}
          </h2>
          <div className="space-y-2.5">
            {payout_requests.map((req: any) => (
              <div key={req.id} className="bg-secondary p-4 rounded-2xl border border-hint/5 flex justify-between items-center shadow-sm">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-hint/5 rounded-xl shrink-0">
                    {getPayoutStatusIcon(req.status)}
                  </div>
                  <div>
                    <span className="text-[11px] font-black text-text-h uppercase tracking-wide block">
                      {formatPrice(parseFloat(req.amount))}
                    </span>
                    <span className="text-[9px] text-hint font-bold block mt-0.5">
                      Karta: {req.card_number.replace(/(\d{4})\d{8}(\d{4})/, '$1 **** **** $2')}
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className={`text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full ${
                    req.status === 'completed' ? 'bg-emerald-500/10 text-emerald-500' :
                    req.status === 'rejected' ? 'bg-rose-500/10 text-rose-500' :
                    'bg-amber-500/10 text-amber-500'
                  }`}>
                    {getPayoutStatusLabel(req.status)}
                  </span>
                  <span className="text-[8px] text-hint font-bold block mt-1">
                    {new Date(req.created_at).toLocaleDateString()}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Ledger Transactions (Debt / Top-up Logs) */}
      <div className="space-y-3">
        <h2 className="text-sm font-black text-text-h uppercase tracking-wider pl-1">
          {language === 'uz' ? 'Hisob-Kitoblar Tarixi' : 'История взаиморасчетов'}
        </h2>
        {ledger_transactions.length === 0 ? (
          <div className="bg-secondary/40 border border-hint/5 p-6 rounded-3xl text-center">
            <p className="text-xs text-hint font-medium">
              {language === 'uz' ? 'Hozircha moliyaviy tranzaksiyalar yo\'q.' : 'Финансовых транзакций пока нет.'}
            </p>
          </div>
        ) : (
          <div className="space-y-2.5">
            {ledger_transactions.map((tx: any) => {
              const amountValue = parseFloat(tx.amount);
              // In this ledger:
              // - positive amount (e.g. +15,000 UZS) represents commission deduction (owes us more)
              // - negative amount (e.g. -100,000 UZS) represents store topup/payment (owes us less)
              const isCommission = tx.type === 'commission' || amountValue > 0;

              return (
                <div key={tx.id} className="bg-secondary p-4 rounded-2xl border border-hint/5 flex justify-between items-center shadow-sm">
                  <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-xl shrink-0 ${isCommission ? 'bg-red-500/10 text-red-500' : 'bg-emerald-500/10 text-emerald-500'}`}>
                      {isCommission ? <TrendingUp className="w-4.5 h-4.5" /> : <TrendingDown className="w-4.5 h-4.5" />}
                    </div>
                    <div>
                      <span className="text-[11px] font-black text-text-h uppercase tracking-wide block">
                        {isCommission ? (language === 'uz' ? 'Buyurtma Komissiyasi' : 'Комиссия с заказа') : (language === 'uz' ? 'Balansni To\'ldirish' : 'Пополнение баланса')}
                      </span>
                      <span className="text-[9px] text-hint font-bold block mt-0.5">{new Date(tx.created_at).toLocaleString()}</span>
                    </div>
                  </div>
                  <div className="text-right">
                    <span className={`text-xs font-black block ${isCommission ? 'text-red-500' : 'text-emerald-500'}`}>
                      {isCommission ? '+' : '-'}{formatPrice(Math.abs(amountValue))}
                    </span>
                    <span className="text-[8px] text-hint font-bold block mt-0.5">
                      {language === 'uz' ? 'Qarzdorlik: ' : 'Задолженность: '}{formatPrice(parseFloat(tx.balance_after))}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Top Up Modal */}
      {showTopupModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 animate-fade-in animate-duration-200">
          <div className="bg-secondary w-full max-w-sm rounded-[32px] p-6 space-y-6 border border-hint/10 shadow-2xl animate-scale-up animate-duration-200">
            <div className="flex justify-between items-center">
              <h3 className="text-base font-black text-text-h uppercase tracking-wide">
                {language === 'uz' ? "Balansni To'ldirish" : "Пополнение баланса"}
              </h3>
              <button
                onClick={() => { hapticFeedback('light'); setShowTopupModal(false); }}
                className="w-7 h-7 rounded-full bg-hint/10 text-text-h font-bold text-xs flex items-center justify-center active:scale-90 transition-transform"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              {/* Amount input */}
              <div className="space-y-1">
                <label className="text-[10px] font-black uppercase text-hint tracking-wide pl-1">
                  {language === 'uz' ? 'To\'lov summasi (UZS)' : 'Сумма оплаты (UZS)'}
                </label>
                <input
                  type="number"
                  value={topupAmount}
                  onChange={(e) => setTopupAmount(e.target.value)}
                  placeholder="Masalan: 50000"
                  className="w-full bg-bg border border-hint/10 focus:border-primary/40 focus:ring-0 rounded-2xl px-4 py-3 text-xs text-text-h font-black placeholder:font-medium placeholder:text-hint/40"
                />
              </div>

              {/* Quick choices */}
              <div className="grid grid-cols-4 gap-1.5">
                {['50000', '100000', '250000', '500000'].map((amt) => (
                  <button
                    key={amt}
                    type="button"
                    onClick={() => { hapticFeedback('light'); setTopupAmount(amt); }}
                    className={`py-2 px-1 rounded-xl text-[9px] font-black border transition-all ${
                      topupAmount === amt ? 'bg-primary text-white border-primary' : 'bg-bg text-text-h border-hint/10'
                    }`}
                  >
                    {(parseInt(amt) / 1000) + 'k'}
                  </button>
                ))}
              </div>

              {/* Payment provider selection */}
              <div className="space-y-2">
                <label className="text-[10px] font-black uppercase text-hint tracking-wide pl-1">
                  {language === 'uz' ? 'To\'lov tizimi' : 'Платежная система'}
                </label>
                <div className="grid grid-cols-2 gap-3">
                  {/* Click */}
                  <button
                    type="button"
                    onClick={() => { hapticFeedback('light'); setTopupProvider('click'); }}
                    className={`p-3 rounded-2xl border-2 flex flex-col items-center gap-1 transition-all ${
                      topupProvider === 'click' ? 'bg-[#0059c0]/10 border-[#0059c0]/55 shadow-sm' : 'bg-bg border-transparent'
                    }`}
                  >
                    <span className="text-lg">🔵</span>
                    <span className="text-[10px] font-extrabold text-text-h">CLICK</span>
                  </button>

                  {/* Payme */}
                  <button
                    type="button"
                    onClick={() => { hapticFeedback('light'); setTopupProvider('payme'); }}
                    className={`p-3 rounded-2xl border-2 flex flex-col items-center gap-1 transition-all ${
                      topupProvider === 'payme' ? 'bg-[#12c8e5]/10 border-[#12c8e5]/55 shadow-sm' : 'bg-bg border-transparent'
                    }`}
                  >
                    <span className="text-lg">🟢</span>
                    <span className="text-[10px] font-extrabold text-text-h">Payme</span>
                  </button>
                </div>
              </div>
            </div>

            <button
              onClick={() => {
                hapticFeedback('medium');
                topupMutation.mutate({
                  amount: parseFloat(topupAmount),
                  provider: topupProvider,
                });
              }}
              disabled={topupMutation.isPending || !topupAmount || parseFloat(topupAmount) < 1000}
              className="w-full bg-primary hover:bg-primary/95 disabled:bg-primary/50 text-white rounded-2xl py-3.5 text-xs font-black shadow-lg shadow-primary/15 flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
            >
              {topupMutation.isPending ? (
                <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              ) : (
                language === 'uz' ? "To'lashga o'tish" : "Перейти к оплате"
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
