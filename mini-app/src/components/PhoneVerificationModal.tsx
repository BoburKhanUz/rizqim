import { useEffect, useState, type FormEvent } from 'react';
import { hapticFeedback } from '../lib/telegram';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { getUzPhoneDigits, UZ_PHONE_PREFIX } from '../lib/phone';
import { useLanguageStore } from '../store/languageStore';
import api from '../services/api';
import type { SendCodeResponse, VerifyCodeResponse } from '../types';

interface PhoneVerificationModalProps {
  // Called with the full number (+998XXXXXXXXX) once the server accepted the SMS code.
  // By then the server has already saved the number on the account.
  onVerified: (fullPhone: string) => void;
  // Shows a close button (changing the phone from a profile page). The first-login prompt can't be dismissed.
  onClose?: () => void;
  // Number to start with, in any format
  initialPhone?: string;
  // SMS verification is off on the server: the phone step saves the number with this callback instead of sending a code
  saveWithoutVerification?: (fullPhone: string) => Promise<void>;
}

// Gorgeous Premium Phone Verification Modal (phone step + SMS code step)
export default function PhoneVerificationModal({ onVerified, onClose, initialPhone, saveWithoutVerification }: PhoneVerificationModalProps) {
  const { language } = useLanguageStore();
  const [phone, setPhone] = useState(() => getUzPhoneDigits(initialPhone) ?? '');
  const [code, setCode] = useState('');
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [countdown, setCountdown] = useState(60);

  const isChange = !!onClose;

  useEffect(() => {
    let timer: ReturnType<typeof setTimeout> | undefined;
    if (step === 'code' && countdown > 0) {
      timer = setTimeout(() => setCountdown(countdown - 1), 1000);
    }
    return () => clearTimeout(timer);
  }, [step, countdown]);

  const handleSendCode = async (e: FormEvent) => {
    e.preventDefault();
    const cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.length !== 9) {
      alert(language === 'uz' ? 'Iltimos, 9 xonali telefon raqamingizni kiriting!' : 'Пожалуйста, введите 9-значный номер телефона!');
      return;
    }

    const fullPhone = `${UZ_PHONE_PREFIX}${cleanPhone}`;
    hapticFeedback('medium');

    try {
      setIsSubmitting(true);

      if (!saveWithoutVerification) {
        const response = await api.post<SendCodeResponse>('/phone/send-code', { phone: fullPhone });
        if (response.data?.success) {
          // If in debug/local mode, show code in alert for developer convenience
          if (response.data.code) {
            alert(`[DEBUG MODE] Tasdiqlash kodi: ${response.data.code}`);
          }
          setStep('code');
          setCountdown(60);
        } else {
          alert(response.data?.message || 'Xatolik yuz berdi.');
        }
      } else {
        // Direct save without SMS if not required
        await saveWithoutVerification(fullPhone);
      }
    } catch (err) {
      logApiError('Failed to send code or save phone number:', err);
      alert(getApiFieldError(err, 'phone') || getApiErrorMessage(err) || (language === 'uz' ? 'Xatolik yuz berdi. Iltimos qaytadan urining.' : 'Произошла ошибка. Пожалуйста, попробуйте еще раз.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerifyCode = async (e: FormEvent) => {
    e.preventDefault();
    if (code.length !== 6) {
      alert(language === 'uz' ? 'Iltimos, 6 xonali kodni kiriting!' : 'Пожалуйста, введите 6-значный код!');
      return;
    }

    const cleanPhone = phone.replace(/\D/g, '');
    const fullPhone = `${UZ_PHONE_PREFIX}${cleanPhone}`;
    hapticFeedback('medium');

    try {
      setIsSubmitting(true);
      const response = await api.post<VerifyCodeResponse>('/phone/verify-code', {
        phone: fullPhone,
        code: code
      });

      if (response.data?.success) {
        onVerified(fullPhone);
      } else {
        alert(response.data?.message || 'Kod noto\'g\'ri.');
      }
    } catch (err) {
      logApiError('Failed to verify code:', err);
      alert(getApiErrorMessage(err) || (language === 'uz' ? 'Kod noto\'g\'ri yoki muddati tugagan.' : 'Код неверный или срок его действия истек.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleResendCode = async () => {
    if (countdown > 0) return;
    const cleanPhone = phone.replace(/\D/g, '');
    const fullPhone = `${UZ_PHONE_PREFIX}${cleanPhone}`;
    hapticFeedback('light');
    try {
      setIsSubmitting(true);
      const response = await api.post<SendCodeResponse>('/phone/send-code', { phone: fullPhone });
      if (response.data?.success) {
        if (response.data.code) {
          alert(`[DEBUG MODE] Yangi tasdiqlash kodi: ${response.data.code}`);
        }
        setCountdown(60);
      }
    } catch (err) {
      alert(getApiFieldError(err, 'phone') || getApiErrorMessage(err) || 'Kodni qayta yuborishda xatolik.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-6 animate-fade-in">
      <div className="relative bg-white w-full max-w-sm rounded-[32px] p-6 shadow-2xl border border-slate-100 flex flex-col gap-5 text-center animate-scale-up">
        {onClose && (
          <button
            type="button"
            onClick={() => { hapticFeedback('light'); onClose(); }}
            disabled={isSubmitting}
            aria-label={language === 'uz' ? 'Yopish' : 'Закрыть'}
            className="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-sm font-black active:scale-90 transition disabled:opacity-50"
          >
            ✕
          </button>
        )}

        <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center text-primary text-3xl mx-auto mb-1 animate-pulse">
          📱
        </div>

        {step === 'phone' ? (
          <>
            <div>
              <h2 className="text-xl font-black text-slate-900 mb-2">
                {isChange
                  ? (language === 'uz' ? 'Yangi telefon raqam' : 'Новый номер телефона')
                  : (language === 'uz' ? 'Telefon raqamingiz' : 'Номер телефона')}
              </h2>
              <p className="text-xs text-slate-500 leading-relaxed">
                {isChange
                  ? (language === 'uz'
                    ? 'Telefon raqamini o\'zgartirish uchun yangi raqamingizga SMS orqali tasdiqlash kodi yuboriladi:'
                    : 'Для смены номера на новый номер будет отправлен SMS-код подтверждения:')
                  : (language === 'uz'
                    ? 'Buyurtmalaringizni tez va to\'g\'ri yetkazib berish uchun iltimos telefon raqamingizni kiriting:'
                    : 'Для быстрой доставки ваших заказов введите ваш номер телефона:')}
              </p>
            </div>

            <form onSubmit={handleSendCode} className="flex flex-col gap-4">
              <div className="flex items-center bg-slate-50 border border-slate-200/80 rounded-2xl px-4 py-3.5 focus-within:border-primary transition-all">
                <span className="text-xs font-black text-slate-800 mr-2 border-r border-slate-200 pr-2">+998</span>
                <input
                  type="tel"
                  placeholder="90 123 45 67"
                  maxLength={9}
                  value={phone}
                  onChange={(e) => setPhone(e.target.value.replace(/\D/g, ''))}
                  className="bg-transparent border-none outline-none font-bold text-xs text-slate-800 w-full tracking-wider placeholder-slate-400"
                  disabled={isSubmitting}
                  autoFocus
                />
              </div>

              <button
                type="submit"
                disabled={isSubmitting || phone.length !== 9}
                className="w-full bg-primary text-white font-extrabold text-xs py-4 rounded-2xl active:scale-95 transition-all shadow-md shadow-primary/20 disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
              >
                {isSubmitting ? (
                  <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <span>{language === 'uz' ? 'Tasdiqlash' : 'Подтвердить'}</span>
                )}
              </button>
            </form>
          </>
        ) : (
          <>
            <div>
              <h2 className="text-xl font-black text-slate-900 mb-2">
                {language === 'uz' ? 'Tasdiqlash kodi' : 'Код подтверждения'}
              </h2>
              <p className="text-xs text-slate-500 leading-relaxed">
                {language === 'uz'
                  ? `+998 ${phone.substring(0,2)} ${phone.substring(2,5)} ${phone.substring(5,7)} ${phone.substring(7,9)} raqamiga SMS kod yuborildi:`
                  : `SMS код был отправлен на номер +998 ${phone}:`}
              </p>
            </div>

            <form onSubmit={handleVerifyCode} className="flex flex-col gap-4">
              <div className="flex items-center bg-slate-50 border border-slate-200/80 rounded-2xl px-4 py-3.5 focus-within:border-primary transition-all">
                <input
                  type="tel"
                  placeholder="123456"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  className="bg-transparent border-none outline-none font-black text-center text-base text-slate-800 w-full tracking-[0.5rem] pl-2 placeholder-slate-400"
                  disabled={isSubmitting}
                  autoFocus
                />
              </div>

              <button
                type="submit"
                disabled={isSubmitting || code.length !== 6}
                className="w-full bg-primary text-white font-extrabold text-xs py-4 rounded-2xl active:scale-95 transition-all shadow-md shadow-primary/20 disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
              >
                {isSubmitting ? (
                  <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <span>{language === 'uz' ? 'Kodni tasdiqlash' : 'Подтвердить код'}</span>
                )}
              </button>

              <div className="flex flex-col gap-2 mt-2">
                {countdown > 0 ? (
                  <span className="text-[11px] text-slate-500">
                    {language === 'uz' ? `Kodni qayta yuborish (${countdown}s)` : `Повторная отправка через (${countdown}с)`}
                  </span>
                ) : (
                  <button
                    type="button"
                    onClick={handleResendCode}
                    disabled={isSubmitting}
                    className="text-[11px] text-primary font-bold hover:underline self-center cursor-pointer"
                  >
                    {language === 'uz' ? 'Kodni qayta yuborish' : 'Отправить код еще раз'}
                  </button>
                )}

                <button
                  type="button"
                  onClick={() => setStep('phone')}
                  disabled={isSubmitting}
                  className="text-[11px] text-slate-500 font-medium hover:underline hover:text-slate-800 self-center cursor-pointer"
                >
                  {language === 'uz' ? 'Raqamni o\'zgartirish' : 'Изменить номер'}
                </button>
              </div>
            </form>
          </>
        )}
      </div>
    </div>
  );
}
