import { useRef, useState } from 'react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { uploadSettingsFile, useOwnerSettings, useSaveOwnerSettings, type OwnerPaymentSettings, type OwnerSettings as OwnerStoreSettings, type OwnerSettingsInput, type PaymentProviderSettings } from '@rizqim/staff';
import { staffApi } from '../services/staffApi';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { useLanguageStore } from '../store/languageStore';
import { Button, Card, Field, InlineError, PageHeader, Section, Skeleton, Spinner, cx, fieldClass } from '../components/ui';
import { FileButton } from '../components/staff/FileButton';
import { Pill } from '../components/staff/Pill';
import { Switch } from '../components/staff/Switch';
import { Camera, Image as ImageIcon, MapPin, Store } from 'lucide-react';

// A provider of payment_settings: a settings object or (legacy data) a bare boolean. The API adds `available: false`
// to a provider that is not integrated yet (Uzum, Paynet): it is shown but can't be switched on.
type ProviderSetting = boolean | PaymentProviderSettings | null | undefined;

// Cash is on unless the store switched it off
function isCashOn(setting: ProviderSetting): boolean {
  if (setting === undefined || setting === null) return true;
  return typeof setting === 'boolean' ? setting : setting.enabled !== false;
}

// An online provider is on only when the store switched it on
function isOnlineOn(setting: ProviderSetting): boolean {
  if (setting === undefined || setting === null) return false;
  return typeof setting === 'boolean' ? setting : setting.enabled === true;
}

function isProviderAvailable(setting: ProviderSetting): boolean {
  return typeof setting === 'object' && setting !== null ? setting.available !== false : true;
}

// A row of the form with a switch: a title, what it does, the switch
interface SwitchRowProps {
  title: string;
  description?: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  // The provider is not integrated yet: the switch stays off and can't be touched
  comingSoon?: boolean;
}

function SwitchRow({ title, description, checked, onChange, comingSoon = false }: SwitchRowProps) {
  return (
    <div className={cx('flex items-center justify-between gap-3 rounded-card bg-surface-2 py-1 pl-4 pr-2', comingSoon && 'opacity-70')}>
      <div className="min-w-0 py-2">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="text-body font-bold text-fg">{title}</h3>
          {comingSoon && <Pill tone="warning">Tez orada</Pill>}
        </div>
        {description && <p className="text-footnote text-fg-muted">{description}</p>}
      </div>
      <Switch checked={checked && !comingSoon} onChange={onChange} label={title} disabled={comingSoon} />
    </div>
  );
}

const storageUrl = (path: string) => (path.startsWith('http') ? path : `/storage/${path}`);

export default function OwnerSettings() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';

  // Fetch current store settings
  const { data: storeInfo, isLoading, refetch, isRefetching } = useOwnerSettings();

  const title = uz ? "Do'kon sozlamalari" : 'Настройки магазина';

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2" role="status" aria-label={uz ? 'Yuklanmoqda' : 'Загрузка'}>
        <PageHeader title={title} back />
        <Skeleton className="h-72 rounded-card" />
        <Skeleton className="h-32 rounded-card" />
      </div>
    );
  }

  // Without the stored settings the form would show (and could save) defaults over the real ones
  if (!storeInfo) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={title} back />
        <InlineError
          title={uz ? "Sozlamalarni yuklab bo'lmadi" : 'Не удалось загрузить настройки'}
          onRetry={() => void refetch()}
          retrying={isRefetching}
        />
      </div>
    );
  }

  return <OwnerSettingsForm storeInfo={storeInfo} />;
}

// Mounted once the settings are loaded, so every field starts from the stored value
function OwnerSettingsForm({ storeInfo }: { storeInfo: OwnerStoreSettings }) {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const formRef = useRef<HTMLFormElement>(null);

  const paySettings: OwnerPaymentSettings = storeInfo.payment_settings ?? {};
  const hours: { open?: string; close?: string } = paySettings.working_hours ?? {};
  const uzumAvailable = isProviderAvailable(paySettings.uzum);
  const paynetAvailable = isProviderAvailable(paySettings.paynet);

  const [logo, setLogo] = useState(storeInfo.logo || '');
  const [banner, setBanner] = useState(storeInfo.banner || '');
  const [description, setDescription] = useState(storeInfo.description || '');
  const [address, setAddress] = useState(storeInfo.address || '');
  const [locationUrl, setLocationUrl] = useState(storeInfo.location_url || '');
  const [isOpen, setIsOpen] = useState(storeInfo.is_open);

  // Upload loading states
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [isUploadingBanner, setIsUploadingBanner] = useState(false);

  // Settings state
  const [deliveryFee, setDeliveryFee] = useState(paySettings.delivery_fee?.toString() || '0');
  const [minOrderAmount, setMinOrderAmount] = useState(storeInfo.min_order_amount?.toString() || '0');
  const [openTime, setOpenTime] = useState(hours.open || '09:00');
  const [closeTime, setCloseTime] = useState(hours.close || '22:00');

  // Gateways status. Uzum and Paynet are not integrated yet: whatever was stored, they start (and stay) off.
  const [cashEnabled, setCashEnabled] = useState(isCashOn(paySettings.cash));
  const [clickEnabled, setClickEnabled] = useState(isOnlineOn(paySettings.click));
  const [paymeEnabled, setPaymeEnabled] = useState(isOnlineOn(paySettings.payme));
  const [uzumEnabled, setUzumEnabled] = useState(uzumAvailable && isOnlineOn(paySettings.uzum));
  const [paynetEnabled, setPaynetEnabled] = useState(paynetAvailable && isOnlineOn(paySettings.paynet));

  // Delivery type toggles
  const [deliveryEnabled, setDeliveryEnabled] = useState(paySettings.delivery_enabled !== false);
  const [pickupEnabled, setPickupEnabled] = useState(paySettings.pickup_enabled !== false);

  const isMethodAllowed = (method: string) => {
    const allowed = storeInfo.allowed_methods || ['cash', 'click', 'payme', 'uzum', 'paynet'];
    return allowed.includes(method);
  };

  // File upload handler
  const handleFileUpload = async (e: ChangeEvent<HTMLInputElement>, type: 'logo' | 'banner') => {
    const file = e.target.files?.[0];
    if (!file) return;

    hapticFeedback('light');
    if (type === 'logo') {
      setIsUploadingLogo(true);
    } else {
      setIsUploadingBanner(true);
    }

    try {
      const data = await uploadSettingsFile(staffApi, file);
      if (type === 'logo') {
        setLogo(data.path);
      } else {
        setBanner(data.path);
      }
      notificationOccurred('success');
    } catch (err) {
      logApiError('Failed to upload file:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || 'Fayl yuklashda xatolik yuz berdi.');
    } finally {
      if (type === 'logo') {
        setIsUploadingLogo(false);
      } else {
        setIsUploadingBanner(false);
      }
    }
  };

  const [isGettingLocation, setIsGettingLocation] = useState(false);

  const handleGetCurrentLocation = () => {
    if (!navigator.geolocation) {
      void showAlert(uz ? 'Brauzeringiz geolokatsiyani qo\'llab-quvvatlamaydi' : 'Ваш браузер не поддерживает геолокацию');
      return;
    }

    hapticFeedback('medium');
    setIsGettingLocation(true);

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const mapUrl = `https://yandex.com/maps/?pt=${lng},${lat}&z=16&l=map`;

        setLocationUrl(mapUrl);
        if (!address) {
          setAddress(uz ? 'GPS joylashuv' : 'GPS локация');
        }

        notificationOccurred('success');
        setIsGettingLocation(false);
        void showAlert(uz ? 'Lokatsiya muvaffaqiyatli aniqlandi!' : 'Локация успешно определена!');
      },
      (error) => {
        logApiError('Failed to get location:', error);
        notificationOccurred('error');
        setIsGettingLocation(false);
        void showAlert(uz ? 'Joylashuvni aniqlash imkoni bo\'lmadi. Iltimos, ruxsat berilganligini tekshiring.' : 'Не удалось определить локацию. Проверьте разрешения.');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      }
    );
  };

  // Mutation to save settings
  const saveSettings = useSaveOwnerSettings({
    onSuccess: () => {
      notificationOccurred('success');
      void showAlert(uz ? 'Sozlamalar muvaffaqiyatli saqlandi!' : 'Настройки успешно сохранены!');
    },
    onError: (err: unknown) => {
      notificationOccurred('error');
      // 422 on payment_settings: a provider that can't be enabled (yet) was switched on
      void showAlert(getApiFieldError(err, 'payment_settings') || getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    }
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');

    const payload: OwnerSettingsInput = {
      logo: logo.trim() || null,
      banner: banner.trim() || null,
      description: description.trim() || null,
      address: address.trim() || null,
      location_url: locationUrl.trim() || null,
      is_open: isOpen,
      min_order_amount: parseFloat(minOrderAmount) || 0,
      payment_settings: {
        delivery_fee: parseFloat(deliveryFee) || 0,
        delivery_enabled: deliveryEnabled,
        pickup_enabled: pickupEnabled,
        working_hours: {
          open: openTime,
          close: closeTime,
        },
        cash: { enabled: cashEnabled },
        click: { enabled: clickEnabled },
        payme: { enabled: paymeEnabled },
        // Never switched on from here while the provider is not integrated (the server refuses it)
        uzum: { enabled: uzumAvailable && uzumEnabled },
        paynet: { enabled: paynetAvailable && paynetEnabled },
      }
    };

    saveSettings.mutate(payload);
  };

  // The one action of the screen, always at the bottom
  useMainButton({
    text: uz ? 'Sozlamalarni saqlash' : 'Сохранить настройки',
    onClick: () => formRef.current?.requestSubmit(),
    progress: saveSettings.isPending,
  });

  const uploadOverlay = (busy: boolean): ReactNode =>
    busy && (
      <span className="absolute inset-0 flex items-center justify-center bg-scrim text-on-brand">
        <Spinner className="size-6" />
      </span>
    );

  return (
    <div className="flex flex-col gap-4 px-4 pt-2">
      <PageHeader
        title={uz ? "Do'kon sozlamalari" : 'Настройки магазина'}
        subtitle={uz ? "Logo, banner, ish vaqti va to'lov tizimlari" : 'Логотип, баннер, часы работы и оплата'}
        back
      />

      <form ref={formRef} onSubmit={handleSubmit} className="flex flex-col gap-5">
        {/* Basic Info */}
        <Section title="Asosiy ma'lumotlar">
          <Card className="flex flex-col gap-4">
            <Field label="Do'kon nomi">
              {(props) => <input {...props} type="text" disabled value={storeInfo?.name || ''} className={fieldClass} />}
            </Field>

            {/* Logo Upload */}
            <div className="flex flex-col gap-1.5">
              <span className="text-footnote font-bold text-fg-muted">Logo</span>
              <div className="flex items-center gap-4 rounded-card bg-surface-2 p-3">
                <div className="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-surface text-fg-muted">
                  {logo ? (
                    <img src={storageUrl(logo)} alt="Logo" className="size-full object-cover" />
                  ) : (
                    <Store aria-hidden="true" className="size-7" />
                  )}
                  {uploadOverlay(isUploadingLogo)}
                </div>
                <div className="flex min-w-0 flex-col gap-1">
                  <FileButton label={uz ? 'Logo yuklash' : 'Загрузить лого'} icon={Camera} accept="image/*" onFile={(e) => handleFileUpload(e, 'logo')} disabled={isUploadingLogo} />
                  <span className="text-caption text-fg-muted">PNG, JPG (maks. 5MB)</span>
                </div>
              </div>
            </div>

            {/* Banner Upload */}
            <div className="flex flex-col gap-1.5">
              <span className="text-footnote font-bold text-fg-muted">Muqova rasmi</span>
              <div className="flex flex-col gap-3 rounded-card bg-surface-2 p-3">
                <div className="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-control border border-line bg-surface text-fg-muted">
                  {banner ? (
                    <img src={storageUrl(banner)} alt="Banner" className="size-full object-cover" />
                  ) : (
                    <span className="flex flex-col items-center gap-1 text-footnote">
                      <ImageIcon aria-hidden="true" className="size-6" />
                      Rasm yo'q
                    </span>
                  )}
                  {uploadOverlay(isUploadingBanner)}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <FileButton label={uz ? 'Banner yuklash' : 'Загрузить баннер'} icon={ImageIcon} accept="image/*" onFile={(e) => handleFileUpload(e, 'banner')} disabled={isUploadingBanner} />
                  <span className="text-caption text-fg-muted">PNG, JPG (maks. 5MB)</span>
                </div>
              </div>
            </div>

            <Field label="Tavsif">
              {(props) => (
                <textarea
                  {...props}
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Do'kon haqida qisqacha ma'lumot..."
                  className={cx(fieldClass, 'resize-none')}
                />
              )}
            </Field>

            <Field label="Do'kon manzili">
              {(props) => (
                <input
                  {...props}
                  type="text"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  placeholder="Masalan: Toshkent sh., Chilonzor tumani, 9-mavze, 23-uy"
                  className={fieldClass}
                />
              )}
            </Field>

            <Field label="Xarita havolasi" hint="Google Maps yoki Yandex Maps havolasi">
              {(props) => (
                <input
                  {...props}
                  type="text"
                  inputMode="url"
                  value={locationUrl}
                  onChange={(e) => setLocationUrl(e.target.value)}
                  placeholder="https://yandex.com/maps/..."
                  className={fieldClass}
                />
              )}
            </Field>
            <Button variant="secondary" icon={MapPin} loading={isGettingLocation} onClick={handleGetCurrentLocation} fullWidth>
              {isGettingLocation ? (uz ? 'Aniqlanmoqda...' : 'Определение...') : uz ? 'Joriy joylashuvni aniqlash' : 'Определить текущую локацию'}
            </Button>

            <SwitchRow
              title="Do'kon ochiq"
              description="Yopiq holatda buyurtma qabul qilinmaydi"
              checked={isOpen}
              onChange={setIsOpen}
            />
          </Card>
        </Section>

        {/* Operating Hours */}
        <Section title="Ish vaqti">
          <Card className="grid grid-cols-2 gap-4">
            <Field label="Ochilish vaqti">
              {(props) => <input {...props} type="time" value={openTime} onChange={(e) => setOpenTime(e.target.value)} className={fieldClass} />}
            </Field>
            <Field label="Yopilish vaqti">
              {(props) => <input {...props} type="time" value={closeTime} onChange={(e) => setCloseTime(e.target.value)} className={fieldClass} />}
            </Field>
          </Card>
        </Section>

        {/* Payment Gateways */}
        <Section title="To'lov tizimlari">
          <Card className="flex flex-col gap-3">
            {isMethodAllowed('cash') && <SwitchRow title="Naqd pul" checked={cashEnabled} onChange={setCashEnabled} />}
            {isMethodAllowed('click') && <SwitchRow title="Click" checked={clickEnabled} onChange={setClickEnabled} />}
            {isMethodAllowed('payme') && <SwitchRow title="Payme" checked={paymeEnabled} onChange={setPaymeEnabled} />}
            {isMethodAllowed('uzum') && <SwitchRow title="Uzum Bank" checked={uzumEnabled} onChange={setUzumEnabled} comingSoon={!uzumAvailable} />}
            {isMethodAllowed('paynet') && <SwitchRow title="Paynet" checked={paynetEnabled} onChange={setPaynetEnabled} comingSoon={!paynetAvailable} />}
          </Card>
        </Section>

        {/* Logistics */}
        <Section title="Yetkazish va olib ketish">
          <Card className="flex flex-col gap-4">
            <SwitchRow
              title="Yetkazib berish"
              description="Mijozlar uyiga buyurtma qilishlari mumkin"
              checked={deliveryEnabled}
              onChange={setDeliveryEnabled}
            />
            <SwitchRow
              title="Olib ketish"
              description="Mijozlar restorandan borib olib ketishlari mumkin"
              checked={pickupEnabled}
              onChange={setPickupEnabled}
            />

            <Field label="Yetkazib berish narxi (so'm)">
              {(props) => (
                <input {...props} type="number" inputMode="numeric" value={deliveryFee} onChange={(e) => setDeliveryFee(e.target.value)} placeholder="Masalan: 12000" className={fieldClass} />
              )}
            </Field>

            <Field label="Minimal buyurtma miqdori (so'm)">
              {(props) => (
                <input {...props} type="number" inputMode="numeric" value={minOrderAmount} onChange={(e) => setMinOrderAmount(e.target.value)} placeholder="Masalan: 30000" className={fieldClass} />
              )}
            </Field>
          </Card>
        </Section>
      </form>
    </div>
  );
}
