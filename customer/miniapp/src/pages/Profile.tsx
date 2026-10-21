import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bike, ChevronRight, Clock, CreditCard, PackageX, ShieldCheck, Store, TriangleAlert, Settings } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useAuthStore } from '../store/authStore';
import { isLanguage, useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { NotificationBell } from '../components/catalog/NotificationBell';
import api from '../services/api';
import PhoneVerificationModal from '../components/PhoneVerificationModal';
import { classifyPhoneChange } from '../lib/phone';
import { getApiFieldError, logApiError } from '../lib/apiError';
import { Button, Card, Field, PageHeader, Section, Skeleton, cx, fieldClass } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { StoreStatusPill } from '../components/staff/StoreStatusPill';
import type { ApiResponse, OwnerStoreSettings } from '../types';

// What the form fields hold while the profile is being edited
interface ProfileDraft {
  name: string;
  phone: string;
  address: string;
}

const storageUrl = (path: string) => (path.startsWith('http') ? path : `/storage/${path}`);

// A row that opens another screen
function LinkRow({ icon: Icon, label, onClick }: { icon: LucideIcon; label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 text-left text-body font-medium text-fg transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
        <Icon aria-hidden="true" className="size-5" />
      </span>
      <span className="flex-1">{label}</span>
      <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
    </button>
  );
}

export default function Profile() {
  const navigate = useNavigate();
  const { user, updateUser, smsVerificationRequired } = useAuthStore();
  const { language, setLanguage } = useLanguageStore();
  const uz = language === 'uz';

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

  // The success note goes away by itself
  useEffect(() => {
    if (!showToast) return;
    const timer = setTimeout(() => setShowToast(false), 3000);
    return () => clearTimeout(timer);
  }, [showToast]);

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
      notificationOccurred('success');
      setShowToast(true);
    } catch (err) {
      logApiError('Failed to update profile:', err);
      const phoneMessage = getApiFieldError(err, 'phone');
      if (phoneMessage) {
        notificationOccurred('error');
        setPhoneError(phoneMessage);
      } else {
        void showAlert(uz ? 'Profilni saqlashda xatolik yuz berdi.' : 'Ошибка при сохранении профиля.');
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
      notificationOccurred('error');
      setPhoneError(change.kind === 'invalid'
        ? (uz ? 'Telefon raqami +998 bilan boshlanib, 9 ta raqamdan iborat bo\'lishi kerak.' : 'Номер телефона должен начинаться с +998 и содержать 9 цифр.')
        : (uz ? 'Tasdiqlangan telefon raqamini o\'chirib bo\'lmaydi.' : 'Подтверждённый номер телефона нельзя удалить.'));
      return;
    }
    if (change.kind === 'changed') {
      setVerifyingPhone(change.fullPhone);
      return;
    }
    await handleSavePersonalProfile(null);
  };

  // While editing, saving is the one action of the screen
  useMainButton({
    text: uz ? 'Saqlash' : 'Сохранить',
    onClick: handleSave,
    progress: isSaving,
    visible: isEditing,
  });

  const title = isOwner
    ? (uz ? 'Do\'kon profili' : 'Профиль магазина')
    : (uz ? 'Mening profilim' : language === 'ru' ? 'Мой профиль' : 'My Profile');

  if (isOwner && storeLoading && !storeInfo) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2" role="status" aria-label={uz ? 'Yuklanmoqda' : 'Загрузка'}>
        <PageHeader title={title} />
        <Skeleton className="h-36 rounded-card" />
        <Skeleton className="h-64 rounded-card" />
      </div>
    );
  }

  const roleLabel = user?.role === 'courier' ? (uz ? 'Kuryer' : 'Курьер') : user?.role === 'manager' ? (uz ? 'Menejer' : 'Менеджер') : user?.role === 'super_admin' ? 'Superadmin' : null;
  const readOnlyLook = !isEditing && 'border-transparent text-fg';

  return (
    <div className="flex flex-col gap-4 px-4 pt-2">
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

      {/* Saved note */}
      {showToast && (
        <div role="status" className="bottom-dock fixed inset-x-4 z-50 mx-auto max-w-sm animate-fade-in-up rounded-card bg-success px-4 py-3 text-center text-body font-bold text-on-success shadow-lg">
          {uz ? 'Sozlamalar muvaffaqiyatli saqlandi' : 'Настройки успешно сохранены'}
        </div>
      )}

      <PageHeader
        title={title}
        action={<NotificationBell />}
      />

      {/* Header card (store profile or the person) */}
      {isOwner ? (
        <div className="flex flex-col gap-2">
          <div className="relative">
            <div className="flex h-36 w-full items-center justify-center overflow-hidden rounded-card border border-line bg-surface-2 text-footnote text-fg-muted">
              {storeInfo?.banner ? (
                <img src={storageUrl(storeInfo.banner)} alt="Banner" className="size-full object-cover" />
              ) : (
                <span>{uz ? "Muqova rasmi yo'q" : 'Нет обложки'}</span>
              )}
            </div>
            {/* Logo positioning */}
            <div className="absolute -bottom-7 left-4 flex size-16 items-center justify-center overflow-hidden rounded-full border-2 border-page bg-surface text-fg-muted shadow">
              {storeInfo?.logo ? (
                <img src={storageUrl(storeInfo.logo)} alt="Logo" className="size-full object-cover" />
              ) : (
                <Store aria-hidden="true" className="size-7" />
              )}
            </div>
          </div>

          <div className="flex items-start justify-between gap-3 px-1 pt-8">
            <div className="min-w-0">
              <h2 className="truncate text-title font-bold text-fg">{storeInfo?.name || (uz ? "Do'kon" : 'Магазин')}</h2>
              {storeInfo?.id !== undefined && <span className="text-footnote text-fg-muted">ID: #{storeInfo.id}</span>}
            </div>
            <StoreStatusPill status={storeInfo?.status} />
          </div>
        </div>
      ) : (
        <Card className="flex flex-col items-center gap-1 text-center">
          <span className="flex size-16 items-center justify-center rounded-full bg-brand-soft text-title font-bold text-brand-soft-fg">
            {name ? name.charAt(0).toUpperCase() : 'U'}
          </span>
          <h2 className="mt-2 text-headline font-bold text-fg">{name || (uz ? 'Foydalanuvchi' : 'Пользователь')}</h2>
          {telegram && <p className="text-footnote text-fg-muted">{telegram}</p>}
          {roleLabel && (
            <Pill tone="brand" icon={user?.role === 'courier' ? Bike : ShieldCheck} className="mt-1">
              {roleLabel}
            </Pill>
          )}
        </Card>
      )}

      {/* Form fields */}
      <Card className="flex flex-col gap-4">
        <Field label={uz ? 'Ism-familiya' : 'Имя и фамилия'}>
          {(props) => (
            <input
              {...props}
              type="text"
              readOnly={!isEditing}
              value={name}
              onChange={(e) => setDraft((current) => current && { ...current, name: e.target.value })}
              className={cx(fieldClass, readOnlyLook)}
            />
          )}
        </Field>

        <Field
          label={uz ? 'Telefon raqam' : 'Номер телефона'}
          error={phoneError || undefined}
          hint={isEditing && smsVerificationRequired ? (uz ? 'Yangi raqam SMS kod orqali tasdiqlanadi' : 'Новый номер будет подтверждён по SMS') : undefined}
        >
          {(props) => (
            <input
              {...props}
              type="tel"
              readOnly={!isEditing}
              placeholder="+998 (90) 123-45-67"
              value={phone}
              onChange={(e) => { setDraft((current) => current && { ...current, phone: e.target.value }); setPhoneError(''); }}
              className={cx(fieldClass, readOnlyLook)}
            />
          )}
        </Field>

        {!isOwner && (
          <Field label={uz ? 'Yetkazish manzili' : 'Адрес доставки'}>
            {(props) => (
              <textarea
                {...props}
                readOnly={!isEditing}
                placeholder={uz ? 'Toshkent sh., Chilonzor tumani...' : 'Ташкент, Чиланзарский район...'}
                value={address}
                onChange={(e) => setDraft((current) => current && { ...current, address: e.target.value })}
                className={cx(fieldClass, readOnlyLook, 'h-20 resize-none')}
              />
            )}
          </Field>
        )}

        {telegram && (
          <Field label="Telegram">
            {(props) => <input {...props} type="text" disabled value={telegram} className={fieldClass} />}
          </Field>
        )}

        {/* App language */}
        <Field label={uz ? 'Ilova tili' : language === 'ru' ? 'Язык приложения' : 'App language'}>
          {(props) => (
            <select
              {...props}
              value={language}
              onChange={(e) => { selectionChanged(); if (isLanguage(e.target.value)) setLanguage(e.target.value); }}
              className={cx(fieldClass, 'cursor-pointer')}
            >
              <option value="uz">O'zbekcha (UZ)</option>
              <option value="ru">Русский (RU)</option>
              <option value="en">English (EN)</option>
            </select>
          )}
        </Field>

        {isEditing ? (
          <Button variant="secondary" onClick={() => { setDraft(null); setPhoneError(''); }} disabled={isSaving} fullWidth>
            {uz ? 'Bekor qilish' : 'Отмена'}
          </Button>
        ) : (
          <Button variant="secondary" onClick={() => { hapticFeedback('light'); setDraft({ name, phone, address }); }} fullWidth>
            {uz ? 'Tahrirlash' : 'Редактировать'}
          </Button>
        )}
      </Card>

      {user?.role === 'manager' && (
        <Section title={uz ? 'Filial' : 'Филиал'}>
          <Card padded={false} className="divide-y divide-line overflow-hidden">
            <LinkRow icon={PackageX} label={uz ? 'Stop-list va qozon' : 'Стоп-лист и котлы'} onClick={() => { hapticFeedback('light'); navigate('/admin/stoplist'); }} />
            <LinkRow icon={Clock} label={uz ? 'Filial ish vaqti' : 'Часы работы филиала'} onClick={() => { hapticFeedback('light'); navigate('/admin/branches'); }} />
          </Card>
        </Section>
      )}

      {user?.role === 'owner' && (
        <Section title={uz ? "Do'kon sozlamalari" : 'Настройки магазина'}>
          <Card padded={false} className="divide-y divide-line overflow-hidden">
            <LinkRow icon={PackageX} label={uz ? 'Stop-list va qozon' : 'Стоп-лист и котлы'} onClick={() => { hapticFeedback('light'); navigate('/admin/stoplist'); }} />
            <LinkRow icon={Clock} label={uz ? 'Filiallar va ish vaqti' : 'Филиалы и часы работы'} onClick={() => { hapticFeedback('light'); navigate('/admin/branches'); }} />
            <LinkRow icon={CreditCard} label={uz ? "Balans va to'lovlar" : 'Баланс и платежи'} onClick={() => { hapticFeedback('light'); navigate('/admin/payments'); }} />
            <LinkRow icon={Settings} label={uz ? 'Tizim va ish vaqti sozlamalari' : 'Настройки системы и часов'} onClick={() => { hapticFeedback('light'); navigate('/admin/settings'); }} />
            <LinkRow icon={TriangleAlert} label={uz ? "Shikoyatlar va e'tirozlar" : 'Жалобы и споры'} onClick={() => { hapticFeedback('light'); navigate('/complaints'); }} />
          </Card>
        </Section>
      )}
    </div>
  );
}
