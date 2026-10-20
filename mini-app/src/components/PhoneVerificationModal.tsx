import { useEffect, useState, type FormEvent } from 'react';
import { hapticFeedback } from '../lib/telegram';
import { showAlert } from '../lib/dialogs';
import { Button, Dialog } from './ui';
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

// The phone number dialog: the phone step, then the SMS code step. Without onClose (first sign-in) it cannot be dismissed.
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
      void showAlert(language === 'uz' ? 'Iltimos, 9 xonali telefon raqamingizni kiriting!' : 'Пожалуйста, введите 9-значный номер телефона!');
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
            void showAlert(`[DEBUG MODE] Tasdiqlash kodi: ${response.data.code}`);
          }
          setStep('code');
          setCountdown(60);
        } else {
          void showAlert(response.data?.message || 'Xatolik yuz berdi.');
        }
      } else {
        // Direct save without SMS if not required
        await saveWithoutVerification(fullPhone);
      }
    } catch (err) {
      logApiError('Failed to send code or save phone number:', err);
      void showAlert(getApiFieldError(err, 'phone') || getApiErrorMessage(err) || (language === 'uz' ? 'Xatolik yuz berdi. Iltimos qaytadan urining.' : 'Произошла ошибка. Пожалуйста, попробуйте еще раз.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerifyCode = async (e: FormEvent) => {
    e.preventDefault();
    if (code.length !== 6) {
      void showAlert(language === 'uz' ? 'Iltimos, 6 xonali kodni kiriting!' : 'Пожалуйста, введите 6-значный код!');
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
        void showAlert(response.data?.message || 'Kod noto\'g\'ri.');
      }
    } catch (err) {
      logApiError('Failed to verify code:', err);
      void showAlert(getApiErrorMessage(err) || (language === 'uz' ? 'Kod noto\'g\'ri yoki muddati tugagan.' : 'Код неверный или срок его действия истек.'));
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
          void showAlert(`[DEBUG MODE] Yangi tasdiqlash kodi: ${response.data.code}`);
        }
        setCountdown(60);
      }
    } catch (err) {
      void showAlert(getApiFieldError(err, 'phone') || getApiErrorMessage(err) || 'Kodni qayta yuborishda xatolik.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const phoneTitle = isChange
    ? (language === 'uz' ? 'Yangi telefon raqam' : 'Новый номер телефона')
    : (language === 'uz' ? 'Telefon raqamingiz' : 'Номер телефона');
  const phoneText = isChange
    ? (language === 'uz'
      ? 'Telefon raqamini o\'zgartirish uchun yangi raqamingizga SMS orqali tasdiqlash kodi yuboriladi:'
      : 'Для смены номера на новый номер будет отправлен SMS-код подтверждения:')
    : (language === 'uz'
      ? 'Buyurtmalaringizni tez va to\'g\'ri yetkazib berish uchun iltimos telefon raqamingizni kiriting:'
      : 'Для быстрой доставки ваших заказов введите ваш номер телефона:');
  const codeText = language === 'uz'
    ? `+998 ${phone.substring(0,2)} ${phone.substring(2,5)} ${phone.substring(5,7)} ${phone.substring(7,9)} raqamiga SMS kod yuborildi:`
    : `SMS код был отправлен на номер +998 ${phone}:`;

  return (
    <Dialog
      open
      variant="center"
      role={isChange ? 'dialog' : 'alertdialog'}
      hideClose={!isChange}
      busy={isSubmitting}
      onClose={() => {
        hapticFeedback('light');
        onClose?.();
      }}
      title={step === 'phone' ? phoneTitle : (language === 'uz' ? 'Tasdiqlash kodi' : 'Код подтверждения')}
      description={step === 'phone' ? phoneText : codeText}
    >
      {step === 'phone' ? (
        <form onSubmit={handleSendCode} className="flex flex-col gap-4">
          <div className="flex min-h-12 items-stretch gap-2 rounded-control border border-line bg-surface-2 px-3 focus-within:border-brand">
            <span className="flex items-center border-r border-line pr-2 text-headline font-bold text-fg">+998</span>
            <input
              type="tel"
              inputMode="numeric"
              autoComplete="tel-national"
              placeholder="90 123 45 67"
              aria-label={language === 'uz' ? 'Telefon raqam' : 'Номер телефона'}
              maxLength={9}
              value={phone}
              onChange={(e) => setPhone(e.target.value.replace(/\D/g, ''))}
              className="min-h-11 min-w-0 flex-1 border-none bg-transparent py-2 text-headline font-medium tracking-wide text-fg outline-none placeholder:text-fg-subtle"
              disabled={isSubmitting}
              data-autofocus
            />
          </div>

          <Button type="submit" size="lg" fullWidth loading={isSubmitting} disabled={phone.length !== 9}>
            {language === 'uz' ? 'Tasdiqlash' : 'Подтвердить'}
          </Button>
        </form>
      ) : (
        <form onSubmit={handleVerifyCode} className="flex flex-col gap-4">
          <input
            type="tel"
            inputMode="numeric"
            autoComplete="one-time-code"
            placeholder="123456"
            aria-label={language === 'uz' ? 'SMS kod' : 'SMS-код'}
            maxLength={6}
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
            className="min-h-12 w-full rounded-control border border-line bg-surface-2 px-3 text-center text-title font-bold tracking-[0.5em] text-fg outline-none placeholder:text-fg-subtle focus:border-brand"
            disabled={isSubmitting}
            data-autofocus
            autoFocus
          />

          <Button type="submit" size="lg" fullWidth loading={isSubmitting} disabled={code.length !== 6}>
            {language === 'uz' ? 'Kodni tasdiqlash' : 'Подтвердить код'}
          </Button>

          <div className="flex flex-col items-center">
            {countdown > 0 ? (
              <span className="flex min-h-11 items-center text-footnote text-fg-muted">
                {language === 'uz' ? `Kodni qayta yuborish (${countdown}s)` : `Повторная отправка через (${countdown}с)`}
              </span>
            ) : (
              <Button variant="ghost" onClick={handleResendCode} disabled={isSubmitting}>
                {language === 'uz' ? 'Kodni qayta yuborish' : 'Отправить код еще раз'}
              </Button>
            )}

            <Button variant="ghost" onClick={() => setStep('phone')} disabled={isSubmitting} className="text-fg-muted">
              {language === 'uz' ? 'Raqamni o\'zgartirish' : 'Изменить номер'}
            </Button>
          </div>
        </form>
      )}
    </Dialog>
  );
}
