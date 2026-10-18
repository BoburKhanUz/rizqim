import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
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

export default function OwnerSettings() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const queryClient = useQueryClient();

  const [logo, setLogo] = useState('');
  const [banner, setBanner] = useState('');
  const [description, setDescription] = useState('');
  const [address, setAddress] = useState('');
  const [locationUrl, setLocationUrl] = useState('');
  const [isOpen, setIsOpen] = useState(true);

  // Upload loading states
  const [isUploadingLogo, setIsUploadingLogo] = useState(false);
  const [isUploadingBanner, setIsUploadingBanner] = useState(false);

  // Settings state
  const [deliveryFee, setDeliveryFee] = useState('0');
  const [minOrderAmount, setMinOrderAmount] = useState('0');
  const [openTime, setOpenTime] = useState('09:00');
  const [closeTime, setCloseTime] = useState('22:00');

  // Gateways status
  const [cashEnabled, setCashEnabled] = useState(true);
  const [clickEnabled, setClickEnabled] = useState(false);
  const [paymeEnabled, setPaymeEnabled] = useState(false);
  const [uzumEnabled, setUzumEnabled] = useState(false);
  const [paynetEnabled, setPaynetEnabled] = useState(false);

  // Delivery type toggles
  const [deliveryEnabled, setDeliveryEnabled] = useState(true);
  const [pickupEnabled, setPickupEnabled] = useState(true);

  // Fetch current store settings
  const { data: storeInfo, isLoading } = useQuery({
    queryKey: ['owner-settings'],
    queryFn: async () => {
      const res = await api.get('/owner/settings');
      return res.data?.data || null;
    }
  });

  // Populate state when data loads
  useEffect(() => {
    if (storeInfo) {
      setLogo(storeInfo.logo || '');
      setBanner(storeInfo.banner || '');
      setDescription(storeInfo.description || '');
      setAddress(storeInfo.address || '');
      setLocationUrl(storeInfo.location_url || '');
      setIsOpen(storeInfo.is_open);
      setMinOrderAmount(storeInfo.min_order_amount?.toString() || '0');

      const paySettings = storeInfo.payment_settings || {};
      setDeliveryFee(paySettings.delivery_fee?.toString() || '0');
      
      const hours = paySettings.working_hours || {};
      setOpenTime(hours.open || '09:00');
      setCloseTime(hours.close || '22:00');

      setCashEnabled(paySettings.cash === true || paySettings.cash?.enabled !== false);
      setClickEnabled(paySettings.click === true || paySettings.click?.enabled === true);
      setPaymeEnabled(paySettings.payme === true || paySettings.payme?.enabled === true);
      setUzumEnabled(paySettings.uzum === true || paySettings.uzum?.enabled === true);
      setPaynetEnabled(paySettings.paynet === true || paySettings.paynet?.enabled === true);

      setDeliveryEnabled(paySettings.delivery_enabled !== false);
      setPickupEnabled(paySettings.pickup_enabled !== false);
    }
  }, [storeInfo]);

  const isMethodAllowed = (method: string) => {
    const allowed = storeInfo?.allowed_methods || ['cash', 'click', 'payme', 'uzum', 'paynet'];
    return allowed.includes(method);
  };

  // File upload handler
  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>, type: 'logo' | 'banner') => {
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
      const res = await api.post('/owner/settings/upload', formData, {
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
    } catch (err: any) {
      logApiError('Failed to upload file:', err);
      hapticFeedback('rigid');
      alert(err.response?.data?.message || 'Fayl yuklashda xatolik yuz berdi.');
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
    mutationFn: async (payload: any) => {
      const res = await api.post('/owner/settings', payload);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      queryClient.invalidateQueries({ queryKey: ['owner-settings'] });
      alert(language === 'uz' ? 'Sozlamalar muvaffaqiyatli saqlandi!' : 'Настройки успешно сохранены!');
    },
    onError: (err: any) => {
      hapticFeedback('rigid');
      alert(err.response?.data?.message || 'Xatolik yuz berdi.');
    }
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');

    const payload = {
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
        uzum: { enabled: uzumEnabled },
        paynet: { enabled: paynetEnabled },
      }
    };

    saveSettings.mutate(payload);
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-screen bg-bg">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

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
            {/* Cash */}
            {isMethodAllowed('cash') && (
              <div className="flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5">
                <div>
                  <h4 className="text-xs font-bold text-text-h">Naqd pul orqali (Cash)</h4>
                </div>
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setCashEnabled(!cashEnabled); }}
                  className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${cashEnabled ? 'bg-primary' : 'bg-hint/20'}`}
                >
                  <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${cashEnabled ? 'translate-x-4.5' : 'translate-x-0'}`} />
                </button>
              </div>
            )}

            {/* Click */}
            {isMethodAllowed('click') && (
              <div className="flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5">
                <div>
                  <h4 className="text-xs font-bold text-text-h">CLICK Tizimi</h4>
                </div>
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setClickEnabled(!clickEnabled); }}
                  className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${clickEnabled ? 'bg-primary' : 'bg-hint/20'}`}
                >
                  <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${clickEnabled ? 'translate-x-4.5' : 'translate-x-0'}`} />
                </button>
              </div>
            )}

            {/* Payme */}
            {isMethodAllowed('payme') && (
              <div className="flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5">
                <div>
                  <h4 className="text-xs font-bold text-text-h">Payme Tizimi</h4>
                </div>
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setPaymeEnabled(!paymeEnabled); }}
                  className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${paymeEnabled ? 'bg-primary' : 'bg-hint/20'}`}
                >
                  <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${paymeEnabled ? 'translate-x-4.5' : 'translate-x-0'}`} />
                </button>
              </div>
            )}

            {/* Uzum */}
            {isMethodAllowed('uzum') && (
              <div className="flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5">
                <div>
                  <h4 className="text-xs font-bold text-text-h">Uzum Bank</h4>
                </div>
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setUzumEnabled(!uzumEnabled); }}
                  className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${uzumEnabled ? 'bg-primary' : 'bg-hint/20'}`}
                >
                  <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${uzumEnabled ? 'translate-x-4.5' : 'translate-x-0'}`} />
                </button>
              </div>
            )}

            {/* Paynet */}
            {isMethodAllowed('paynet') && (
              <div className="flex items-center justify-between p-3 bg-bg/40 rounded-2xl border border-white/5">
                <div>
                  <h4 className="text-xs font-bold text-text-h">Paynet</h4>
                </div>
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setPaynetEnabled(!paynetEnabled); }}
                  className={`w-10 h-5.5 rounded-full p-0.5 transition-colors duration-200 ${paynetEnabled ? 'bg-primary' : 'bg-hint/20'}`}
                >
                  <div className={`w-4.5 h-4.5 rounded-full bg-white transition-transform duration-200 ${paynetEnabled ? 'translate-x-4.5' : 'translate-x-0'}`} />
                </button>
              </div>
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
