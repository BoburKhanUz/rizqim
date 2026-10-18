import { useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { hapticFeedback } from '../lib/telegram';
import { useLanguageStore } from '../store/languageStore';
import {
  ArrowLeft,
  Store,
  Clock,
  CreditCard,
  Truck,
  Save,
  Loader2
} from 'lucide-react';
import type { ApiResponse, OwnerStoreSettings, PaymentProviderSettings, PaymentSettings } from '../types';

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

// Body of POST /owner/settings
interface OwnerSettingsPayload {
  logo: string | null;
  banner: string | null;
  description: string | null;
  address: string | null;
  location_url: string | null;
  is_open: boolean;
  min_order_amount: number;
  payment_settings: {
    delivery_fee: number;
    delivery_enabled: boolean;
    pickup_enabled: boolean;
    working_hours: { open: string; close: string };
    cash: { enabled: boolean };
    click: { enabled: boolean };
    payme: { enabled: boolean };
    uzum: { enabled: boolean };
    paynet: { enabled: boolean };
  };
}

interface UploadedFile {
  path: string;
  url: string;
}

interface PaymentToggleProps {
  title: string;
  enabled: boolean;
  onToggle: () => void;
  // The provider is not integrated yet: the switch stays off and can't be touched
  comingSoon?: boolean;
}

function PaymentToggle({ title, enabled, onToggle, comingSoon = false }: PaymentToggleProps) {
  const isOn = enabled && !comingSoon;

  return (
    <div className={`flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5 ${comingSoon ? 'opacity-60' : ''}`}>
      <div className="flex items-center gap-2">
        <h4 className="text-xs font-bold text-text-h">{title}</h4>
        {comingSoon && (
          <span className="text-[9px] font-black bg-amber-500/15 text-amber-600 px-2 py-0.5 rounded-full">
            Tez orada
          </span>
        )}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={isOn}
        aria-label={title}
        disabled={comingSoon}
        onClick={() => { hapticFeedback('light'); onToggle(); }}
        className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${isOn ? 'bg-primary' : 'bg-hint/20'} ${comingSoon ? 'cursor-not-allowed' : ''}`}
      >
        <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${isOn ? 'translate-x-4.5' : 'translate-x-0'}`} />
      </button>
    </div>
  );
}

export default function OwnerSettings() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();

  // Fetch current store settings
  const { data: storeInfo, isLoading, refetch } = useQuery({
    queryKey: ['owner-settings'],
    queryFn: async () => {
      const res = await api.get<ApiResponse<OwnerStoreSettings>>('/owner/settings');
      return res.data?.data || null;
    }
  });

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-screen bg-bg">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  // Without the stored settings the form would show (and could save) defaults over the real ones
  if (!storeInfo) {
    return (
      <div className="p-6 flex flex-col items-center justify-center gap-3 min-h-screen bg-bg text-center">
        <p className="text-sm font-bold text-text-h">
          {language === 'uz' ? "Sozlamalarni yuklab bo'lmadi" : 'Не удалось загрузить настройки'}
        </p>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => { hapticFeedback('light'); navigate(-1); }}
            className="px-4 py-2.5 rounded-2xl bg-secondary text-text-h text-xs font-bold border border-white/5 active:scale-95 transition"
          >
            {language === 'uz' ? 'Orqaga' : 'Назад'}
          </button>
          <button
            type="button"
            onClick={() => { hapticFeedback('light'); refetch(); }}
            className="px-4 py-2.5 rounded-2xl bg-primary text-white text-xs font-bold active:scale-95 transition"
          >
            {language === 'uz' ? 'Qayta urinish' : 'Повторить'}
          </button>
        </div>
      </div>
    );
  }

  return <OwnerSettingsForm storeInfo={storeInfo} />;
}

// Mounted once the settings are loaded, so every field starts from the stored value
function OwnerSettingsForm({ storeInfo }: { storeInfo: OwnerStoreSettings }) {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const queryClient = useQueryClient();

  const paySettings: PaymentSettings = storeInfo.payment_settings ?? {};
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
    const formData = new FormData();
    formData.append('file', file);

    if (type === 'logo') {
      setIsUploadingLogo(true);
    } else {
      setIsUploadingBanner(true);
    }

    try {
      const res = await api.post<ApiResponse<UploadedFile>>('/owner/settings/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      const data = res.data?.data;
      if (type === 'logo') {
        setLogo(data.path);
      } else {
        setBanner(data.path);
      }
      hapticFeedback('success');
    } catch (err) {
      logApiError('Failed to upload file:', err);
      hapticFeedback('rigid');
      alert(getApiErrorMessage(err) || 'Fayl yuklashda xatolik yuz berdi.');
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
      alert(language === 'uz' ? 'Brauzeringiz geolokatsiyani qo\'llab-quvvatlamaydi' : 'Ваш браузер не поддерживает геолокацию');
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
          setAddress(language === 'uz' ? 'GPS Joylashuv 📍' : 'GPS Локация 📍');
        }

        hapticFeedback('success');
        setIsGettingLocation(false);
        alert(language === 'uz' ? 'Lokatsiya muvaffaqiyatli aniqlandi!' : 'Локация успешно определена!');
      },
      (error) => {
        logApiError('Failed to get location:', error);
        hapticFeedback('rigid');
        setIsGettingLocation(false);
        alert(language === 'uz' ? 'Joylashuvni aniqlash imkoni bo\'lmadi. Iltimos, ruxsat berilganligini tekshiring.' : 'Не удалось определить локацию. Проверьте разрешения.');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      }
    );
  };

  // Mutation to save settings
  const saveSettings = useMutation({
    mutationFn: async (payload: OwnerSettingsPayload) => {
      const res = await api.post('/owner/settings', payload);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      queryClient.invalidateQueries({ queryKey: ['owner-settings'] });
      alert(language === 'uz' ? 'Sozlamalar muvaffaqiyatli saqlandi!' : 'Настройки успешно сохранены!');
    },
    onError: (err: unknown) => {
      hapticFeedback('rigid');
      // 422 on payment_settings: a provider that can't be enabled (yet) was switched on
      alert(getApiFieldError(err, 'payment_settings') || getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    }
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');

    const payload: OwnerSettingsPayload = {
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

  return (
    <div className="p-4 pb-28 flex flex-col gap-6 bg-gradient-to-br from-bg via-bg to-primary/5 min-h-screen text-text">
      {/* Header */}
      <header className="flex items-center gap-4 pb-2 border-b border-white/5">
        <button 
          onClick={() => { hapticFeedback('light'); navigate(-1); }}
          className="p-2 rounded-xl bg-secondary text-text-h flex items-center justify-center shadow-sm border border-white/5 active:scale-90 transition"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div>
          <h1 className="text-xl font-bold text-text-h">
            {language === 'uz' ? 'Do\'kon sozlamalari' : 'Настройки магазина'}
          </h1>
          <span className="text-[10px] text-hint font-medium">
            Logo, banner, ish vaqti va to'lov tizimlarini sozlash
          </span>
        </div>
      </header>

      <form onSubmit={handleSubmit} className="flex flex-col gap-6">
        {/* Basic Info */}
        <section className="bg-secondary p-5 rounded-[24px] border border-white/5 shadow-sm flex flex-col gap-4">
          <div className="flex items-center gap-2 text-text-h">
            <Store className="w-5 h-5 text-primary" />
            <h2 className="text-xs uppercase font-black tracking-wider">Asosiy ma'lumotlar</h2>
          </div>

          <div className="flex flex-col gap-3.5">
            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Do'kon nomi</label>
              <input 
                type="text" 
                disabled 
                value={storeInfo?.name || ''} 
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-hint outline-none opacity-60"
              />
            </div>

            {/* Logo Upload */}
            <div className="flex flex-col gap-1.5">
              <label className="text-[9px] font-black text-hint uppercase">Logo</label>
              <div className="flex items-center gap-4 bg-bg/50 p-3.5 rounded-[20px] border border-white/5">
                <div className="relative w-16 h-16 rounded-full bg-secondary border border-white/10 flex items-center justify-center overflow-hidden shrink-0 shadow-inner">
                  {logo ? (
                    <img 
                      src={logo.startsWith('http') ? logo : `/storage/${logo}`} 
                      alt="Logo" 
                      className="w-full h-full object-cover" 
                    />
                  ) : (
                    <span className="text-xl">🏪</span>
                  )}
                  {isUploadingLogo && (
                    <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                      <Loader2 className="w-5 h-5 animate-spin text-primary" />
                    </div>
                  )}
                </div>
                <div className="flex flex-col gap-1">
                  <label className="cursor-pointer bg-secondary border border-white/10 hover:border-primary/50 hover:text-primary px-4 py-2 rounded-xl text-xs font-bold text-text-h shadow-sm active:scale-95 transition-all text-center">
                    {language === 'uz' ? "Logo yuklash 📸" : "Загрузить лого 📸"}
                    <input 
                      type="file" 
                      accept="image/*" 
                      onChange={(e) => handleFileUpload(e, 'logo')} 
                      className="hidden" 
                    />
                  </label>
                  <span className="text-[8px] text-hint font-medium">PNG, JPG (maks. 5MB)</span>
                </div>
              </div>
            </div>

            {/* Banner Upload */}
            <div className="flex flex-col gap-1.5">
              <label className="text-[9px] font-black text-hint uppercase">Muqova rasmi (Banner)</label>
              <div className="flex flex-col gap-3 bg-bg/50 p-3.5 rounded-[20px] border border-white/5">
                <div className="relative w-full h-32 rounded-xl bg-secondary border border-white/10 flex items-center justify-center overflow-hidden">
                  {banner ? (
                    <img 
                      src={banner.startsWith('http') ? banner : `/storage/${banner}`} 
                      alt="Banner" 
                      className="w-full h-full object-cover" 
                    />
                  ) : (
                    <span className="text-xl text-hint/30">Rasm yo'q</span>
                  )}
                  {isUploadingBanner && (
                    <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                      <Loader2 className="w-6 h-6 animate-spin text-primary" />
                    </div>
                  )}
                </div>
                <div className="flex items-center justify-between">
                  <label className="cursor-pointer bg-secondary border border-white/10 hover:border-primary/50 hover:text-primary px-4 py-2 rounded-xl text-xs font-bold text-text-h shadow-sm active:scale-95 transition-all">
                    {language === 'uz' ? "Banner yuklash 🖼️" : "Загрузить баннер 🖼️"}
                    <input 
                      type="file" 
                      accept="image/*" 
                      onChange={(e) => handleFileUpload(e, 'banner')} 
                      className="hidden" 
                    />
                  </label>
                  <span className="text-[8px] text-hint font-medium">PNG, JPG (maks. 5MB)</span>
                </div>
              </div>
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Tavsif (Description)</label>
              <textarea 
                rows={3} 
                value={description} 
                onChange={(e) => setDescription(e.target.value)} 
                placeholder="Do'kon haqida qisqacha ma'lumot..."
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition resize-none"
              />
            </div>

            {/* Store Address */}
            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Do'kon manzili (Matn ko'rinishida)</label>
              <input 
                type="text" 
                value={address} 
                onChange={(e) => setAddress(e.target.value)} 
                placeholder="Masalan: Toshkent sh., Chilonzor tumani, 9-mavze, 23-uy..."
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition"
              />
            </div>

            {/* Location URL */}
            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Xarita havolasi (Google Maps yoki Yandex Maps)</label>
              <div className="flex flex-col gap-2">
                <input 
                  type="text" 
                  value={locationUrl} 
                  onChange={(e) => setLocationUrl(e.target.value)} 
                  placeholder="https://yandex.com/maps/... yoki https://maps.app.goo.gl/..."
                  className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition w-full"
                />
                <button
                  type="button"
                  disabled={isGettingLocation}
                  onClick={handleGetCurrentLocation}
                  className="w-full bg-primary/10 border border-primary/20 text-primary py-3 rounded-2xl font-bold text-xs active:scale-95 transition flex justify-center items-center gap-2"
                >
                  {isGettingLocation ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin text-primary" />
                      <span>{language === 'uz' ? 'Aniqlanmoqda...' : 'Определение...'}</span>
                    </>
                  ) : (
                    <>
                      <span>📍</span>
                      <span>{language === 'uz' ? 'Joriy joylashuvni aniqlash' : 'Определить текущую локацию'}</span>
                    </>
                  )}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between p-3.5 bg-bg/40 rounded-2xl border border-white/5">
              <div>
                <h4 className="text-xs font-bold text-text-h">Do'kon holati (Ochiq/Yopiq)</h4>
                <p className="text-[9px] text-hint mt-0.5">Yopiq holatda buyurtma qabul qilinmaydi</p>
              </div>
              <button
                type="button"
                onClick={() => { hapticFeedback('light'); setIsOpen(!isOpen); }}
                className={`w-12 h-6 rounded-full p-1 transition-colors duration-200 ${isOpen ? 'bg-emerald-500' : 'bg-hint/20'}`}
              >
                <div className={`w-4 h-4 rounded-full bg-white transition-transform duration-200 ${isOpen ? 'translate-x-6' : 'translate-x-0'}`} />
              </button>
            </div>
          </div>
        </section>

        {/* Operating Hours */}
        <section className="bg-secondary p-5 rounded-[24px] border border-white/5 shadow-sm flex flex-col gap-4">
          <div className="flex items-center gap-2 text-text-h">
            <Clock className="w-5 h-5 text-primary" />
            <h2 className="text-xs uppercase font-black tracking-wider">Ish vaqti</h2>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Ochilish vaqti</label>
              <input 
                type="time" 
                value={openTime} 
                onChange={(e) => setOpenTime(e.target.value)} 
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Yopilish vaqti</label>
              <input 
                type="time" 
                value={closeTime} 
                onChange={(e) => setCloseTime(e.target.value)} 
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition"
              />
            </div>
          </div>
        </section>

        {/* Payment Gateways */}
        <section className="bg-secondary p-5 rounded-[24px] border border-white/5 shadow-sm flex flex-col gap-4">
          <div className="flex items-center gap-2 text-text-h">
            <CreditCard className="w-5 h-5 text-primary" />
            <h2 className="text-xs uppercase font-black tracking-wider">To'lov tizimlari</h2>
          </div>

          <div className="flex flex-col gap-3">
            {isMethodAllowed('cash') && (
              <PaymentToggle title="Naqd pul orqali (Cash)" enabled={cashEnabled} onToggle={() => setCashEnabled(!cashEnabled)} />
            )}
            {isMethodAllowed('click') && (
              <PaymentToggle title="CLICK Tizimi" enabled={clickEnabled} onToggle={() => setClickEnabled(!clickEnabled)} />
            )}
            {isMethodAllowed('payme') && (
              <PaymentToggle title="Payme Tizimi" enabled={paymeEnabled} onToggle={() => setPaymeEnabled(!paymeEnabled)} />
            )}
            {isMethodAllowed('uzum') && (
              <PaymentToggle title="Uzum Bank" enabled={uzumEnabled} onToggle={() => setUzumEnabled(!uzumEnabled)} comingSoon={!uzumAvailable} />
            )}
            {isMethodAllowed('paynet') && (
              <PaymentToggle title="Paynet" enabled={paynetEnabled} onToggle={() => setPaynetEnabled(!paynetEnabled)} comingSoon={!paynetAvailable} />
            )}
          </div>
        </section>

        {/* Logistics */}
        <section className="bg-secondary p-5 rounded-[24px] border border-white/5 shadow-sm flex flex-col gap-4">
          <div className="flex items-center gap-2 text-text-h">
            <Truck className="w-5 h-5 text-primary" />
            <h2 className="text-xs uppercase font-black tracking-wider">Logistika va yetkazish</h2>
          </div>

          <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between p-3.5 bg-bg/40 rounded-2xl border border-white/5">
              <div>
                <h4 className="text-xs font-bold text-text-h">Yetkazib berish (Delivery)</h4>
                <p className="text-[9px] text-hint mt-0.5">Mijozlar uyiga buyurtma qilishlari mumkin</p>
              </div>
              <button
                type="button"
                onClick={() => { hapticFeedback('light'); setDeliveryEnabled(!deliveryEnabled); }}
                className={`w-12 h-6 rounded-full p-1 transition-colors duration-200 ${deliveryEnabled ? 'bg-primary' : 'bg-hint/20'}`}
              >
                <div className={`w-4 h-4 rounded-full bg-white transition-transform duration-200 ${deliveryEnabled ? 'translate-x-6' : 'translate-x-0'}`} />
              </button>
            </div>

            <div className="flex items-center justify-between p-3.5 bg-bg/40 rounded-2xl border border-white/5">
              <div>
                <h4 className="text-xs font-bold text-text-h">Olib ketish (Takeaway/Pickup)</h4>
                <p className="text-[9px] text-hint mt-0.5">Mijozlar restorandan borib olib ketishlari mumkin</p>
              </div>
              <button
                type="button"
                onClick={() => { hapticFeedback('light'); setPickupEnabled(!pickupEnabled); }}
                className={`w-12 h-6 rounded-full p-1 transition-colors duration-200 ${pickupEnabled ? 'bg-primary' : 'bg-hint/20'}`}
              >
                <div className={`w-4 h-4 rounded-full bg-white transition-transform duration-200 ${pickupEnabled ? 'translate-x-6' : 'translate-x-0'}`} />
              </button>
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Yetkazib berish narxi (UZS)</label>
              <input 
                type="number" 
                value={deliveryFee} 
                onChange={(e) => setDeliveryFee(e.target.value)} 
                placeholder="Masalan: 12000"
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition"
              />
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-[9px] font-black text-hint uppercase">Minimal buyurtma miqdori (UZS)</label>
              <input 
                type="number" 
                value={minOrderAmount} 
                onChange={(e) => setMinOrderAmount(e.target.value)} 
                placeholder="Masalan: 30000"
                className="bg-bg border border-white/5 p-3 rounded-2xl text-xs font-semibold text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition"
              />
            </div>
          </div>
        </section>

        {/* Submit */}
        <button
          type="submit"
          disabled={saveSettings.isPending}
          className="w-full bg-primary text-white py-4 rounded-3xl font-extrabold text-sm active:scale-95 transition shadow-lg shadow-primary/20 flex justify-center items-center gap-2 disabled:opacity-70 disabled:active:scale-100"
        >
          {saveSettings.isPending ? (
            <Loader2 className="w-5 h-5 animate-spin" />
          ) : (
            <>
              <Save className="w-4 h-4" />
              <span>Sozlamalarni saqlash</span>
            </>
          )}
        </button>
      </form>
    </div>
  );
}
