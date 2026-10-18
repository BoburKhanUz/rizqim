import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCartStore } from '../store/cartStore';
import { hapticFeedback, tg } from '../lib/telegram';
import { useMutation, useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useLanguageStore } from '../store/languageStore';
import { 
  ArrowLeft, 
  ShieldCheck, 
  Check, 
  Bike, 
  ShoppingBag 
} from 'lucide-react';

export default function Checkout() {
  const { 
    items, 
    getSubtotal, 
    clearCart, 
    specialInstructions 
  } = useCartStore();

  const navigate = useNavigate();
  const { t, language } = useLanguageStore();

  const cartStoreId = items[0]?.storeId;

  const { data: stores = [] } = useQuery({
    queryKey: ['checkout-stores'],
    queryFn: async () => {
      const res = await api.get('/stores');
      return res.data?.data || [];
    },
    enabled: !!cartStoreId,
  });

  const store = stores.find((s: any) => s.id.toString() === cartStoreId);

  const isStoreLocked = store?.status === 'payment_required' || 
    (store?.balance !== undefined && store?.credit_limit !== undefined && store.balance >= store.credit_limit);

  const deliveryEnabled = store 
    ? (store.payment_settings?.delivery_enabled !== false && 
       store.payment_settings?.delivery_enabled !== 'false' && 
       store.payment_settings?.delivery_enabled !== 0 && 
       store.payment_settings?.delivery_enabled !== '0')
    : false;

  const pickupEnabled = store 
    ? (store.payment_settings?.pickup_enabled !== false && 
       store.payment_settings?.pickup_enabled !== 'false' && 
       store.payment_settings?.pickup_enabled !== 0 && 
       store.payment_settings?.pickup_enabled !== '0')
    : false;

  const [type, setType] = useState<'delivery' | 'pickup'>('delivery');

  useEffect(() => {
    if (store) {
      if (!deliveryEnabled && pickupEnabled) {
        setType('pickup');
      } else if (deliveryEnabled && !pickupEnabled) {
        setType('delivery');
      }
    }
  }, [store, deliveryEnabled, pickupEnabled]);

  const [address, setAddress] = useState('');
  
  // Dynamically resolve enabled gateways strictly from store allowed methods and owner configurations
  const allowedMethods = store?.payment_settings?.allowed_methods || ['cash', 'click', 'payme', 'uzum', 'paynet'];

  const cashEnabled = (store?.payment_settings?.cash === true || store?.payment_settings?.cash?.enabled !== false) && allowedMethods.includes('cash');
  const clickEnabled = (store?.payment_settings?.click === true || store?.payment_settings?.click?.enabled === true) && allowedMethods.includes('click');
  const paymeEnabled = (store?.payment_settings?.payme === true || store?.payment_settings?.payme?.enabled === true) && allowedMethods.includes('payme');
  const paynetEnabled = (store?.payment_settings?.paynet === true || store?.payment_settings?.paynet?.enabled === true) && allowedMethods.includes('paynet');
  const uzumEnabled = (store?.payment_settings?.uzum === true || store?.payment_settings?.uzum?.enabled === true) && allowedMethods.includes('uzum');

  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'click' | 'payme' | 'paynet' | 'uzum'>('cash');

  useEffect(() => {
    if (store?.payment_settings) {
      if (store.payment_settings.payme === true || store.payment_settings.payme?.enabled === true) {
        setPaymentMethod('payme');
      } else if (store.payment_settings.click === true || store.payment_settings.click?.enabled === true) {
        setPaymentMethod('click');
      } else if (store.payment_settings.uzum === true || store.payment_settings.uzum?.enabled === true) {
        setPaymentMethod('uzum');
      } else if (store.payment_settings.paynet === true || store.payment_settings.paynet?.enabled === true) {
        setPaymentMethod('paynet');
      } else {
        setPaymentMethod('cash');
      }
    }
  }, [store]);
  const [isLocating, setIsLocating] = useState(false);

  const handleShareLocation = () => {
    hapticFeedback('light');
    if (!navigator.geolocation) {
      alert(language === 'uz' ? 'Geolokatsiya brauzeringiz tomonidan qo‘llab-quvvatlanmaydi.' : 'Геолокация не поддерживается вашим браузером.');
      return;
    }

    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setIsLocating(false);
        const { latitude, longitude } = position.coords;
        const yandexLink = `https://yandex.com/maps/?pt=${longitude},${latitude}&z=16&l=map`;
        
        hapticFeedback('medium');
        setAddress(prev => {
          const prefix = prev ? prev.trim() + '\n' : '';
          return `${prefix}📍 Lokatsiya: ${yandexLink}`;
        });
      },
      (error) => {
        setIsLocating(false);
        let errorMsg = language === 'uz' ? 'Lokatsiyani olishda xatolik yuz berdi. Iltimos, ruxsat berilganini tekshiring.' : 'Ошибка при получении локации. Пожалуйста, проверьте разрешения.';
        if (error.code === error.PERMISSION_DENIED) {
          errorMsg = language === 'uz' ? 'Iltimos, mini-ilovaga geolokatsiyadan foydalanishga ruxsat bering.' : 'Пожалуйста, разрешите мини-приложению доступ к геолокации.';
        }
        alert(errorMsg);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const createOrder = useMutation({
    mutationFn: async (payload: any) => {
      const response = await api.post('/orders', payload);
      return response.data;
    },
    onSuccess: (data) => {
      hapticFeedback('heavy');
      clearCart();
      
      const paymentUrl = data.data.payment_url;
      if (paymentUrl) {
        if (tg.openLink && typeof tg.openLink === 'function') {
          tg.openLink(paymentUrl);
        } else {
          window.open(paymentUrl, '_blank');
        }
      }

      // Navigate to success page with order info
      navigate('/success', { state: { order_uuid: data.data.order_uuid, status: data.data.status }, replace: true });
    },
    onError: (error: any) => {
      hapticFeedback('rigid');
      alert(error.response?.data?.message || (language === 'uz' ? 'Buyurtma berishda xatolik yuz berdi.' : language === 'ru' ? 'Ошибка при оформлении заказа.' : 'Failed to place order.'));
    }
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');

    if (isStoreLocked) {
      alert(language === 'uz' 
        ? "Do'kon vaqtincha to'lov muammolari sababli buyurtma qabul qilmayapti." 
        : "Магазин временно не принимает заказы из-за проблем с оплатой.");
      return;
    }

    if (type === 'delivery' && !address.trim()) {
      alert(language === 'uz' ? 'Iltimos, yetkazib berish manzilini kiriting.' : language === 'ru' ? 'Пожалуйста, введите адрес доставки.' : 'Please enter a delivery address.');
      return;
    }

    const payload = {
      store_id: parseInt(cartStoreId),
      type,
      payment_method: paymentMethod,
      address: type === 'delivery' ? address : null,
      notes: specialInstructions.trim() ? specialInstructions : null,
      items: items.map(item => ({
        variant_id: item.id,
        quantity: item.quantity
      }))
    };

    createOrder.mutate(payload);
  };

  const deliveryFee = type === 'delivery' ? (store?.delivery_fee !== undefined && store?.delivery_fee !== null ? store.delivery_fee : (parseFloat(store?.payment_settings?.delivery_fee) || 0)) : 0;
  const total = getSubtotal() + deliveryFee;

  if (items.length === 0) {
    return (
      <div className="p-4 text-center mt-10">
        <h2 className="text-xl font-bold">{t('emptyCart')}</h2>
        <button onClick={() => navigate('/')} className="mt-4 text-primary font-bold">{t('goBack')}</button>
      </div>
    );
  }

  return (
    <div className="p-4 pb-36 flex flex-col gap-6">
      {/* Header */}
      <header className="flex items-center gap-4 pb-2 border-b border-hint/10">
        <button 
          onClick={() => { hapticFeedback('light'); navigate(-1); }} 
          className="p-2 rounded-xl bg-secondary/50 active:scale-90 transition-transform"
        >
          <ArrowLeft className="w-5 h-5 text-text-h" />
        </button>
        <div>
          <h1 className="text-xl font-bold text-text-h">{t('checkout')}</h1>
          <span className="text-[10px] text-hint font-medium">To'lov va Yetkazib berish</span>
        </div>
      </header>

      {isStoreLocked && (
        <div className="bg-rose-500/10 p-4 rounded-3xl flex flex-col gap-1.5 text-xs text-rose-600 border border-rose-500/20 shadow-sm shadow-rose-500/5 animate-pulse">
          <div className="flex items-center gap-2 font-black">
            <span>⚠️</span>
            <span>{language === 'uz' ? "DO'KON VAQTINCHA BLOKLANGAN" : 'МАГАЗИН ВРЕМЕННО ЗАБЛОКИРОВАН'}</span>
          </div>
          <p className="font-semibold text-rose-500/95 leading-relaxed">
            {language === 'uz' 
              ? "Do'kon vaqtincha to'lov muammolari (qarz limitidan oshganlik) sababli buyurtma qabul qilmayapti. Savat ma'lumotlarini saqlab, keyinroq urinib ko'ring."
              : "Магазин временно не принимает заказы из-за проблем с оплатой (превышен лимит долга). Пожалуйста, попробуйте позже."}
          </p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-6">
        {/* Delivery Type Segmented Tabs */}
        {(deliveryEnabled || pickupEnabled) && (
          <section>
            <h2 className="text-xs uppercase font-black text-hint tracking-wider mb-2.5">{t('orderType')}</h2>
            <div className="flex gap-3 bg-secondary p-1 rounded-2xl">
              {deliveryEnabled && (
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setType('delivery'); }}
                  className={`flex-1 py-3 rounded-xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5 ${
                    type === 'delivery' ? 'bg-primary text-white shadow-sm' : 'text-hint'
                  }`}
                >
                  <Bike className="w-4 h-4" />
                  <span>{t('delivery')}</span>
                </button>
              )}
              {pickupEnabled && (
                <button
                  type="button"
                  onClick={() => { hapticFeedback('light'); setType('pickup'); }}
                  className={`flex-1 py-3 rounded-xl font-bold text-xs transition-colors flex items-center justify-center gap-1.5 ${
                    type === 'pickup' ? 'bg-primary text-white shadow-sm' : 'text-hint'
                  }`}
                >
                  <ShoppingBag className="w-4 h-4" />
                  <span>{t('pickup')}</span>
                </button>
              )}
            </div>
          </section>
        )}

        {/* Delivery Address Box */}
        {type === 'delivery' && (
          <section className="animate-fade-in-up flex flex-col gap-2">
            <div className="flex justify-between items-center">
              <h2 className="text-xs uppercase font-black text-hint tracking-wider">{t('deliveryAddress')}</h2>
              <button
                type="button"
                onClick={handleShareLocation}
                disabled={isLocating}
                className="text-[10px] bg-primary/10 text-primary px-3 py-1.5 rounded-full font-bold flex items-center gap-1 hover:bg-primary/20 transition-all active:scale-95 disabled:opacity-50"
              >
                {isLocating ? (
                  <span className="w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <span>📍</span>
                )}
                <span>
                  {isLocating 
                    ? (language === 'uz' ? 'Olinmoqda...' : 'Получение...') 
                    : (language === 'uz' ? 'Lokatsiya ulashish' : language === 'ru' ? 'Поделиться локацией' : 'Share Location')
                  }
                </span>
              </button>
            </div>
            <div className="relative">
              <textarea 
                rows={2}
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                placeholder={t('enterAddress')}
                className="w-full bg-secondary text-text-h placeholder-hint p-4 rounded-2xl outline-none focus:ring-2 focus:ring-primary/25 transition-all resize-none border border-hint/5 text-xs font-semibold leading-relaxed"
              />
            </div>
          </section>
        )}

        {/* Security Banner */}
        <div className="bg-emerald-500/10 p-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold text-emerald-500 border border-emerald-500/20 shadow-sm shadow-emerald-500/5">
          <ShieldCheck className="w-5 h-5 shrink-0" />
          <span>Xavfsiz va sertifikatlangan Telegram to'lov tizimlari.</span>
        </div>

        {/* Payment Methods */}
        <section className="flex flex-col gap-2.5">
          <h2 className="text-xs uppercase font-black text-hint tracking-wider">{t('paymentMethod')}</h2>
          <div className="flex flex-col gap-2.5">
            {/* Payme */}
            {paymeEnabled && (
              <label className={`flex items-center justify-between p-4 rounded-3xl cursor-pointer border-2 transition-all ${
                paymentMethod === 'payme' ? 'bg-[#12c8e5]/10 border-[#12c8e5]/55 shadow-sm shadow-[#12c8e5]/5' : 'bg-secondary border-transparent'
              }`}>
                <div className="flex items-center space-x-3.5">
                  <input type="radio" name="payment" checked={paymentMethod === 'payme'} onChange={() => setPaymentMethod('payme')} className="hidden" />
                  <div className="w-10 h-10 rounded-2xl bg-[#12c8e5] flex items-center justify-center shrink-0 shadow-sm border border-[#12c8e5]/10">
                    <svg viewBox="0 0 24 24" className="w-5.5 h-5.5 text-white" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="2" y="5" width="20" height="14" rx="2" />
                      <line x1="2" y1="10" x2="22" y2="10" />
                      <line x1="6" y1="15" x2="10" y2="15" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-xs font-extrabold text-text-h">Payme</h4>
                    <p className="text-[10px] text-hint mt-0.5">Payme xavfsiz to'lov hamyoni</p>
                  </div>
                </div>
                <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${paymentMethod === 'payme' ? 'border-[#12c8e5]' : 'border-hint/40'}`}>
                  {paymentMethod === 'payme' && <div className="w-2 h-2 bg-[#12c8e5] rounded-full"></div>}
                </div>
              </label>
            )}

            {/* Click */}
            {clickEnabled && (
              <label className={`flex items-center justify-between p-4 rounded-3xl cursor-pointer border-2 transition-all ${
                paymentMethod === 'click' ? 'bg-[#0059c0]/10 border-[#0059c0]/55 shadow-sm shadow-[#0059c0]/5' : 'bg-secondary border-transparent'
              }`}>
                <div className="flex items-center space-x-3.5">
                  <input type="radio" name="payment" checked={paymentMethod === 'click'} onChange={() => setPaymentMethod('click')} className="hidden" />
                  <div className="w-10 h-10 rounded-2xl bg-[#0059c0] flex items-center justify-center shrink-0 shadow-sm border border-[#0059c0]/10">
                    <svg viewBox="0 0 24 24" className="w-5 h-5 text-white fill-white">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-xs font-extrabold text-text-h">CLICK</h4>
                    <p className="text-[10px] text-hint mt-0.5">Click tizimi orqali to'lash</p>
                  </div>
                </div>
                <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${paymentMethod === 'click' ? 'border-[#0059c0]' : 'border-hint/40'}`}>
                  {paymentMethod === 'click' && <div className="w-2 h-2 bg-[#0059c0] rounded-full"></div>}
                </div>
              </label>
            )}

            {/* Uzum Bank / Uzum Pay */}
            {uzumEnabled && (
              <label className={`flex items-center justify-between p-4 rounded-3xl cursor-pointer border-2 transition-all ${
                paymentMethod === 'uzum' ? 'bg-[#7000ff]/10 border-[#7000ff]/55 shadow-sm shadow-[#7000ff]/5' : 'bg-secondary border-transparent'
              }`}>
                <div className="flex items-center space-x-3.5">
                  <input type="radio" name="payment" checked={paymentMethod === 'uzum'} onChange={() => setPaymentMethod('uzum')} className="hidden" />
                  <div className="w-10 h-10 rounded-2xl bg-[#7000ff] flex items-center justify-center shrink-0 shadow-sm border border-[#7000ff]/10">
                    <svg viewBox="0 0 24 24" className="w-5.5 h-5.5 text-white" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M5 4v8a7 7 0 0 0 14 0V4" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-xs font-extrabold text-text-h">Uzum Bank</h4>
                    <p className="text-[10px] text-hint mt-0.5">Uzum hamyon orqali to'lash</p>
                  </div>
                </div>
                <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${paymentMethod === 'uzum' ? 'border-[#7000ff]' : 'border-hint/40'}`}>
                  {paymentMethod === 'uzum' && <div className="w-2 h-2 bg-[#7000ff] rounded-full"></div>}
                </div>
              </label>
            )}

            {/* Paynet */}
            {paynetEnabled && (
              <label className={`flex items-center justify-between p-4 rounded-3xl cursor-pointer border-2 transition-all ${
                paymentMethod === 'paynet' ? 'bg-[#009f3c]/10 border-[#009f3c]/55 shadow-sm shadow-[#009f3c]/5' : 'bg-secondary border-transparent'
              }`}>
                <div className="flex items-center space-x-3.5">
                  <input type="radio" name="payment" checked={paymentMethod === 'paynet'} onChange={() => setPaymentMethod('paynet')} className="hidden" />
                  <div className="w-10 h-10 rounded-2xl bg-[#009f3c] flex items-center justify-center shrink-0 shadow-sm border border-[#009f3c]/10">
                    <svg viewBox="0 0 24 24" className="w-5.5 h-5.5 text-white" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-xs font-extrabold text-text-h">Paynet</h4>
                    <p className="text-[10px] text-hint mt-0.5">Paynet terminal to'lovi</p>
                  </div>
                </div>
                <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${paymentMethod === 'paynet' ? 'border-[#009f3c]' : 'border-hint/40'}`}>
                  {paymentMethod === 'paynet' && <div className="w-2 h-2 bg-[#009f3c] rounded-full"></div>}
                </div>
              </label>
            )}

            {/* Cash on Delivery */}
            {cashEnabled && (
              <label className={`flex items-center justify-between p-4 rounded-3xl cursor-pointer border-2 transition-all ${
                paymentMethod === 'cash' ? 'bg-[#eab308]/10 border-[#eab308]/55 shadow-sm shadow-[#eab308]/5' : 'bg-secondary border-transparent'
              }`}>
                <div className="flex items-center space-x-3.5">
                  <input type="radio" name="payment" checked={paymentMethod === 'cash'} onChange={() => setPaymentMethod('cash')} className="hidden" />
                  <div className="w-10 h-10 rounded-2xl bg-[#eab308] flex items-center justify-center shrink-0 shadow-sm border border-[#eab308]/10">
                    <svg viewBox="0 0 24 24" className="w-5.5 h-5.5 text-white" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="2" y="6" width="20" height="12" rx="2" />
                      <circle cx="12" cy="12" r="2" />
                      <line x1="6" y1="12" x2="6.01" y2="12" />
                      <line x1="18" y1="12" x2="18.01" y2="12" />
                    </svg>
                  </div>
                  <div>
                    <h4 className="text-xs font-extrabold text-text-h">{language === 'uz' ? 'Naqd pul' : 'Наличные'}</h4>
                    <p className="text-[10px] text-hint mt-0.5">Kuryer taomni topshirganda naqd to'lash</p>
                  </div>
                </div>
                <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${paymentMethod === 'cash' ? 'border-[#eab308]' : 'border-hint/40'}`}>
                  {paymentMethod === 'cash' && <div className="w-2 h-2 bg-[#eab308] rounded-full"></div>}
                </div>
              </label>
            )}
          </div>
        </section>

        {/* Pricing Summary */}
        <section className="bg-secondary p-5 rounded-3xl flex flex-col gap-3 border border-hint/5 shadow-sm">
          <div className="flex justify-between items-center text-xs">
            <span className="text-hint">{t('subtotal')}</span>
            <span className="font-extrabold text-text-h">{getSubtotal().toLocaleString()} UZS</span>
          </div>

          <div className="flex justify-between items-center text-xs">
            <span className="text-hint">{t('deliveryFee')}</span>
            {deliveryFee > 0 ? (
              <span className="font-extrabold text-text-h">{deliveryFee.toLocaleString()} UZS</span>
            ) : (
              <span className="bg-emerald-500/10 text-emerald-500 text-[9px] font-black px-2.5 py-0.5 rounded-full border border-emerald-500/20">
                {language === 'uz' ? 'BEPUL' : language === 'ru' ? 'БЕСПЛАТНО' : 'FREE'}
              </span>
            )}
          </div>
        </section>

        {/* Sticky Submit Button */}
        <div className="fixed bottom-0 left-0 right-0 p-4 bg-bg/95 backdrop-blur-md border-t border-hint/10 z-40 max-w-md mx-auto safe-area-bottom">
          <div className="flex justify-between mb-4 px-2 items-center">
            <span className="text-text-h font-extrabold text-sm">{t('total')}</span>
            <span className="text-primary font-black text-xl">{total.toLocaleString()} UZS</span>
          </div>
          <button 
            type="submit"
            disabled={createOrder.isPending || isStoreLocked}
            className={`w-full text-white py-4 rounded-3xl font-extrabold text-sm transition-transform flex justify-center items-center gap-2 disabled:opacity-70 ${
              isStoreLocked ? 'bg-slate-400 active:scale-100 shadow-none' : 'bg-primary active:scale-95 shadow-lg shadow-primary/20'
            }`}
          >
            {createOrder.isPending ? (
              <><span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span> {t('processing')}</>
            ) : isStoreLocked ? (
              <span>{language === 'uz' ? "Do'kon vaqtincha yopiq" : 'Магазин временно закрыт'}</span>
            ) : (
              <>
                <Check className="w-4 h-4 stroke-[3]" />
                <span>To'lash: {total.toLocaleString()} UZS</span>
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
