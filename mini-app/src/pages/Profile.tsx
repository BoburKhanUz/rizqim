import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { isLanguage, useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { User as UserIcon, Phone, MapPin, Save, Globe, Loader2, Bell } from 'lucide-react';
import api from '../services/api';
import PhoneVerificationModal from '../components/PhoneVerificationModal';
import { classifyPhoneChange } from '../lib/phone';
import { getApiFieldError, logApiError } from '../lib/apiError';
import type { ApiResponse, OwnerStoreSettings } from '../types';

// What the form fields hold while the profile is being edited
interface ProfileDraft {
  name: string;
  phone: string;
  address: string;
}

export default function Profile() {
  const navigate = useNavigate();
  const { user, updateUser, smsVerificationRequired } = useAuthStore();
  const { language, setLanguage } = useLanguageStore();

  const isOwner = user?.role === 'owner';

  // Personal profile: the fields show the saved user, except while editing, when they show the draft
  const [draft, setDraft] = useState<ProfileDraft | null>(null);
  const isEditing = draft !== null;
  const name = draft?.name ?? user?.name ?? '';
  const phone = draft?.phone ?? user?.phone ?? '';
  const address = draft?.address ?? user?.address ?? '';
  const telegram = user?.telegram_username ? `@${user.telegram_username}` : '';

  // Store profile local state
  const [storeInfo, setStoreInfo] = useState<OwnerStoreSettings | null>(null);
  const [storeLoading, setStoreLoading] = useState(isOwner);

  const [isSaving, setIsSaving] = useState(false);
  const [showToast, setShowToast] = useState(false);

  // Phone changes go through the SMS flow when the server requires verification
  const [verifyingPhone, setVerifyingPhone] = useState<string | null>(null);
  const [phoneError, setPhoneError] = useState('');

  // Fetch store settings for Owner
  useEffect(() => {
    if (isOwner) {
      const fetchStoreSettings = async () => {
        try {
          setStoreLoading(true);
          const res = await api.get<ApiResponse<OwnerStoreSettings>>('/owner/settings');
          const data = res.data?.data;
          if (data) {
            setStoreInfo(data);
          }
        } catch (err) {
          logApiError('Failed to load store settings:', err);
        } finally {
          setStoreLoading(false);
        }
      };
      fetchStoreSettings();
    }
  }, [user, isOwner]);

  // `phoneToSave` is null when the saved phone must stay untouched (it only changes through the SMS flow)
  const handleSavePersonalProfile = async (phoneToSave: string | null) => {
    try {
      setIsSaving(true);
      setPhoneError('');
      const fields = phoneToSave === null ? { name, address } : { name, phone: phoneToSave, address };

      // Save permanently to backend database
      await api.post('/user/profile', fields);

      // Update local Zustand store
      updateUser(fields);

      setDraft(null);
      setShowToast(true);
      setTimeout(() => setShowToast(false), 3000);
    } catch (err) {
      logApiError('Failed to update profile:', err);
      const phoneMessage = getApiFieldError(err, 'phone');
      if (phoneMessage) {
        hapticFeedback('error');
        setPhoneError(phoneMessage);
      } else {
        alert(language === 'uz' ? 'Profilni saqlashda xatolik yuz berdi.' : 'Ошибка при сохранении профиля.');
      }
    } finally {
      setIsSaving(false);
    }
  };

  const handleSave = async () => {
    hapticFeedback('medium');
    setPhoneError('');

    // SMS verification is off: save everything directly, as before
    if (!smsVerificationRequired) {
      await handleSavePersonalProfile(phone);
      return;
    }

    // SMS verification is on: the server only accepts a new phone that went through the SMS flow
    const change = classifyPhoneChange(phone, user?.phone);
    if (change.kind === 'invalid' || change.kind === 'cleared') {
      hapticFeedback('error');
      setPhoneError(change.kind === 'invalid'
        ? (language === 'uz' ? 'Telefon raqami +998 bilan boshlanib, 9 ta raqamdan iborat bo\'lishi kerak.' : 'Номер телефона должен начинаться с +998 и содержать 9 цифр.')
        : (language === 'uz' ? 'Tasdiqlangan telefon raqamini o\'chirib bo\'lmaydi.' : 'Подтверждённый номер телефона нельзя удалить.'));
      return;
    }
    if (change.kind === 'changed') {
      setVerifyingPhone(change.fullPhone);
      return;
    }
    await handleSavePersonalProfile(null);
  };

  if (isOwner && storeLoading && !storeInfo) {
    return (
      <div className="flex justify-center items-center min-h-screen bg-bg">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  return (
    <div className="p-4 flex flex-col gap-6 min-h-screen pb-24">
      {verifyingPhone && (
        <PhoneVerificationModal
          initialPhone={verifyingPhone}
          onClose={() => setVerifyingPhone(null)}
          onVerified={(fullPhone) => {
            setVerifyingPhone(null);
            setDraft((current) => current && { ...current, phone: fullPhone });
            updateUser({ phone: fullPhone }); // the server already saved it when the code was verified
            handleSavePersonalProfile(null); // name / address
          }}
        />
      )}

      {/* Toast Alert */}
      {showToast && (
        <div className="fixed top-6 left-1/2 -translate-x-1/2 z-50 w-11/12 max-w-xs animate-fade-in-up">
          <div className="bg-emerald-500 text-white px-4 py-3 rounded-2xl shadow-lg text-xs font-bold text-center border border-emerald-400/20">
            {language === 'uz' ? 'Sozlamalar muvaffaqiyatli saqlandi! 🎉' : 'Настройки успешно сохранены! 🎉'}
          </div>
        </div>
      )}

      <header className="flex justify-between items-center pb-2 border-b border-hint/10">
        <h1 className="text-2xl font-bold text-fg">
          {isOwner 
            ? (language === 'uz' ? 'Do\'kon profili' : 'Профиль магазина')
            : (language === 'uz' ? 'Mening profilim' : language === 'ru' ? 'Мой профиль' : 'My Profile')}
        </h1>
        <button
          onClick={() => { hapticFeedback('light'); navigate('/notifications'); }}
          className="w-10 h-10 rounded-2xl bg-secondary border border-white/5 flex items-center justify-center text-fg active:scale-95 transition relative shrink-0 shadow-sm"
        >
          <Bell className="w-4.5 h-4.5" />
          <span className="absolute top-2.5 right-2.5 w-2 h-2 bg-rose-500 rounded-full border-2 border-secondary"></span>
        </button>
      </header>

      {/* Header Cards (Store Profile or Customer Card) */}
      {isOwner ? (
        <div className="flex flex-col gap-4">
          {/* Store Brand/Banner Card */}
          <div className="relative">
            <div className="w-full h-36 bg-secondary border border-white/5 rounded-[24px] overflow-hidden flex items-center justify-center relative shadow-sm">
              {storeInfo?.banner ? (
                <img 
                  src={storeInfo.banner.startsWith('http') ? storeInfo.banner : `/storage/${storeInfo.banner}`} 
                  alt="Banner" 
                  className="w-full h-full object-cover" 
                />
              ) : (
                <span className="text-hint/30 text-[10px] font-bold uppercase tracking-wider">Muqova rasmi (Banner)</span>
              )}
            </div>
            {/* Logo positioning */}
            <div className="absolute -bottom-6 left-6 w-16 h-16 rounded-full bg-secondary border-2 border-bg shadow-lg flex items-center justify-center overflow-hidden">
              {storeInfo?.logo ? (
                <img 
                  src={storeInfo.logo.startsWith('http') ? storeInfo.logo : `/storage/${storeInfo.logo}`} 
                  alt="Logo" 
                  className="w-full h-full object-cover" 
                />
              ) : (
                <span className="text-xl">🏪</span>
              )}
            </div>
          </div>

          <div className="pt-4 px-2 flex justify-between items-start">
            <div>
              <h2 className="text-xl font-black text-fg">{storeInfo?.name || 'Do\'kon'}</h2>
              <span className="text-[10px] text-hint font-medium">ID: #{storeInfo?.id}</span>
            </div>
            <div className="flex flex-col items-end gap-1">
              {storeInfo?.status === 'active' && (
                <span className="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider">
                  {language === 'uz' ? 'Faol' : 'Активно'}
                </span>
              )}
              {storeInfo?.status === 'payment_required' && (
                <span className="bg-rose-500/10 text-rose-400 border border-rose-500/20 text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider animate-pulse">
                  {language === 'uz' ? 'To\'lov kutilmoqda' : 'Требуется оплата'}
                </span>
              )}
              {storeInfo?.status === 'suspended' && (
                <span className="bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider">
                  {language === 'uz' ? 'Muzlatilgan' : 'Приостановлен'}
                </span>
              )}
            </div>
          </div>
        </div>
      ) : (
        /* Personal Profile Card */
        <div className="bg-secondary p-5 rounded-3xl flex flex-col items-center text-center shadow-sm border border-hint/5 relative overflow-hidden">
          {user?.role === 'courier' && (
            <div className="absolute top-3 right-3 bg-purple-500/10 text-purple-500 text-[9px] font-black px-2.5 py-0.5 rounded-full border border-purple-500/20">
              {language === 'uz' ? '🚴 KURYER' : language === 'ru' ? '🚴 КУРЬЕР' : '🚴 COURIER'}
            </div>
          )}
          <div className="w-16 h-16 bg-primary/10 border border-primary/20 rounded-full flex items-center justify-center text-primary text-2xl font-black mb-3">
            {name ? name.charAt(0).toUpperCase() : 'U'}
          </div>
          <h3 className="font-bold text-fg text-lg mb-0.5">{name || 'Foydalanuvchi'}</h3>
          <p className="text-xs text-hint">{telegram || '@telegram_user'}</p>
        </div>
      )}

      {/* Form Fields Section */}
      <div className="bg-secondary p-5 rounded-3xl flex flex-col gap-4 border border-hint/5">
        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
            <UserIcon className="w-3.5 h-3.5 text-primary" />
            {language === 'uz' ? 'Foydalanuvchi ismi' : 'Имя пользователя'}
          </label>
          <input
            type="text"
            disabled={!isEditing}
            value={name}
            onChange={(e) => setDraft((current) => current && { ...current, name: e.target.value })}
            className="w-full px-4 py-3 rounded-2xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none focus:border-primary disabled:opacity-75"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
            <Phone className="w-3.5 h-3.5 text-primary" />
            {language === 'uz' ? 'Telefon raqam' : 'Номер телефона'}
          </label>
          <input
            type="text"
            disabled={!isEditing}
            placeholder="+998 (90) 123-45-67"
            value={phone}
            onChange={(e) => { setDraft((current) => current && { ...current, phone: e.target.value }); setPhoneError(''); }}
            className="w-full px-4 py-3 rounded-2xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none focus:border-primary disabled:opacity-75"
          />
          {phoneError ? (
            <p className="text-[10px] text-rose-500 font-bold pl-1">{phoneError}</p>
          ) : isEditing && smsVerificationRequired && (
            <p className="text-[10px] text-hint font-medium pl-1">
              {language === 'uz' ? 'Yangi raqam SMS kod orqali tasdiqlanadi' : 'Новый номер будет подтверждён по SMS'}
            </p>
          )}
        </div>

        {!isOwner && (
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
              <MapPin className="w-3.5 h-3.5 text-primary" />
              {language === 'uz' ? 'Yetkazish manzili' : 'Адрес доставки'}
            </label>
            <textarea
              disabled={!isEditing}
              placeholder={language === 'uz' ? 'Toshkent sh., Chilonzor tumani...' : 'Ташкент, Чиланзарский район...'}
              value={address}
              onChange={(e) => setDraft((current) => current && { ...current, address: e.target.value })}
              className="w-full px-4 py-3 rounded-2xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none focus:border-primary disabled:opacity-75 h-20 resize-none leading-relaxed"
            />
          </div>
        )}

        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
            <Globe className="w-3.5 h-3.5 text-primary" />
            Telegram
          </label>
          <input
            type="text"
            disabled={true}
            value={telegram}
            className="w-full px-4 py-3 rounded-2xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none opacity-60"
          />
        </div>

        {/* Dynamic App Language Dropdown */}
        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
            <Globe className="w-3.5 h-3.5 text-primary" />
            {language === 'uz' ? 'Ilova tili' : language === 'ru' ? 'Язык приложения' : 'App Language'}
          </label>
          <div className="relative">
            <select
              value={language}
              onChange={(e) => { hapticFeedback('light'); if (isLanguage(e.target.value)) setLanguage(e.target.value); }}
              className="w-full px-4 py-3 rounded-2xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none focus:border-primary appearance-none cursor-pointer"
            >
              <option value="uz">O'zbekcha (UZ)</option>
              <option value="ru">Русский (RU)</option>
              <option value="en">English (EN)</option>
            </select>
            <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-hint font-bold text-[10px]">
              ▼
            </div>
          </div>
        </div>

        <div className="pt-2">
          {isEditing ? (
            <button
              onClick={handleSave}
              disabled={isSaving}
              className="w-full bg-primary text-on-brand font-extrabold text-xs py-3.5 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all shadow-md shadow-primary/20 disabled:opacity-75"
            >
              {isSaving ? (
                <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              ) : (
                <Save className="w-4 h-4" />
              )}
              <span>{language === 'uz' ? 'Saqlash' : 'Сохранить'}</span>
            </button>
          ) : (
            <button
              onClick={() => { hapticFeedback('light'); setDraft({ name, phone, address }); }}
              className="w-full bg-primary/10 border border-primary/20 text-primary font-extrabold text-xs py-3.5 rounded-2xl active:scale-95 transition-all"
            >
              {language === 'uz' ? 'Tahrirlash' : 'Редактировать'}
            </button>
          )}
        </div>
      </div>

      {user?.role === 'owner' && (
        <div className="bg-secondary p-5 rounded-3xl flex flex-col gap-3 border border-hint/5">
          <h4 className="text-[10px] font-black uppercase tracking-wider pl-1 text-hint">
            {language === 'uz' ? 'Do\'kon Sozlamalari' : 'Настройки магазина'}
          </h4>
          <button
            onClick={() => { hapticFeedback('light'); navigate('/admin/payments'); }}
            className="w-full bg-primary/10 border border-primary/20 text-primary font-extrabold text-xs py-3.5 rounded-2xl active:scale-95 transition-all text-center"
          >
            {language === 'uz' ? 'Balans va To\'lovlar 💳' : 'Баланс и Платежи 💳'}
          </button>
          <button
            onClick={() => { hapticFeedback('light'); navigate('/admin/settings'); }}
            className="w-full bg-primary/10 border border-primary/20 text-primary font-extrabold text-xs py-3.5 rounded-2xl active:scale-95 transition-all text-center"
          >
            {language === 'uz' ? 'Tizim va Ish vaqti Sozlamalari ⚙️' : 'Настройки Системы и Часов ⚙️'}
          </button>
          <button
            onClick={() => { hapticFeedback('light'); navigate('/complaints'); }}
            className="w-full bg-primary/10 border border-primary/20 text-primary font-extrabold text-xs py-3.5 rounded-2xl active:scale-95 transition-all text-center"
          >
            {language === 'uz' ? 'Shikoyatlar va E\'tirozlar ⚠️' : 'Жалобы и Споры ⚠️'}
          </button>
        </div>
      )}
    </div>
  );
}
