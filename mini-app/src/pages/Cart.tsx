import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import { hapticFeedback, tg } from '../lib/telegram';
import api from '../services/api';
import { Trash2, Minus, Plus, ShoppingBag, CheckCircle2, PlusCircle, ArrowLeft } from 'lucide-react';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { useAuthStore } from '../store/authStore';


function formatUzbekSum(value: number | string | undefined) {
  if (value === undefined || value === null) return "0 so'm";
  const numValue = Math.round(Number(value));
  return numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " so'm";
}

function getProductBasePrice(product: any): number {
  if (!product) return 0;
  if (product.price && parseFloat(product.price) > 0) {
    return parseFloat(product.price);
  }
  if (product.variants && product.variants.length > 0) {
    const prices = product.variants.map((v: any) => parseFloat(v.price)).filter((p: number) => !isNaN(p) && p > 0);
    if (prices.length > 0) {
      return Math.min(...prices);
    }
  }
  return 0;
}

export default function Cart() {
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { items, storeName, storeAddress, updateQuantity, removeItem, clearCart, getSubtotal, addItem } = useCartStore();
  const { addresses, selectedAddressId, setSelectedAddressId, addAddress } = useAddressStore();

  const [deliveryNote, setDeliveryNote] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('click');
  const [promoCodeInput, setPromoCodeInput] = useState('');
  const [activePromo, setActivePromo] = useState<any>(null);
  const [promoError, setPromoError] = useState('');

  const [storeProducts, setStoreProducts] = useState<any[]>([]);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<any>(null);

  const [deliveryMethod, setDeliveryMethod] = useState<'delivery' | 'pickup'>('delivery');
  const [storeBranches, setStoreBranches] = useState<any[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [storeDeliveryFee, setStoreDeliveryFee] = useState(15000);
  const [isLocating, setIsLocating] = useState(false);

  const [storeDetail, setStoreDetail] = useState<any>(null);

  const [showAddressForm, setShowAddressForm] = useState(false);
  const [newAddress, setNewAddress] = useState({
    title: 'Yangi manzil',
    address: '',
    lat: 40.7821,
    lng: 72.3442,
    apartment: '',
    entrance: '',
    floor: '',
    intercom: '',
    comment: ''
  });

  const [isOrdering, setIsOrdering] = useState(false);
  const [orderSuccess, setOrderSuccess] = useState(false);
  const [successOrderNumber, setSuccessOrderNumber] = useState('');

  // Scroll to top on mount
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, []);

  // Fetch store branches on mount/items change
  useEffect(() => {
    if (items.length > 0) {
      const storeId = items[0].storeId;
      api.get('/stores').then(res => {
        if (res.data?.success) {
          const matchedStore = res.data.data.find((s: any) => s.id.toString() === storeId);
          if (matchedStore) {
            setStoreDetail(matchedStore);
            if (matchedStore.branches) {
              setStoreBranches(matchedStore.branches);
              if (matchedStore.branches.length > 0) {
                const mainBranch = matchedStore.branches[0];
                setSelectedBranchId(mainBranch.id);
                setNewAddress(prev => ({
                  ...prev,
                  lat: parseFloat(mainBranch.latitude) || 41.316755,
                  lng: parseFloat(mainBranch.longitude) || 69.123402
                }));
              }
            }
            const rawFee = matchedStore.delivery_fee !== undefined && matchedStore.delivery_fee !== null
              ? matchedStore.delivery_fee
              : (matchedStore.payment_settings ? parseFloat(matchedStore.payment_settings.delivery_fee) : NaN);
            setStoreDeliveryFee(isNaN(rawFee) ? 15000 : rawFee);
          }
        }
      }).catch(err => {
        console.error('Failed to fetch store branches', err);
      });
    }
  }, [items]);

  // Calculate dynamic allowed payment methods based on store's admin settings
  const availablePaymentMethods = useMemo(() => {
    if (!storeDetail) return ['click', 'payme', 'naqd'];
    
    const settings = storeDetail.payment_settings || {};
    const allowed = settings.allowed_methods || ['cash', 'click', 'payme'];
    
    const methods: string[] = [];
    
    // Check cash
    if (allowed.includes('cash') || allowed.includes('naqd')) {
      const isEnabled = settings.cash ? settings.cash.enabled !== false : true;
      if (isEnabled) {
        methods.push('naqd');
      }
    }
    
    // Check click
    if (allowed.includes('click')) {
      const isEnabled = settings.click ? settings.click.enabled === true : true;
      if (isEnabled) {
        methods.push('click');
      }
    }
    
    // Check payme
    if (allowed.includes('payme')) {
      const isEnabled = settings.payme ? settings.payme.enabled === true : true;
      if (isEnabled) {
        methods.push('payme');
      }
    }
    
    // Check uzum
    if (allowed.includes('uzum')) {
      const isEnabled = settings.uzum ? settings.uzum.enabled === true : true;
      if (isEnabled) {
        methods.push('uzum');
      }
    }

    // Check paynet
    if (allowed.includes('paynet')) {
      const isEnabled = settings.paynet ? settings.paynet.enabled === true : true;
      if (isEnabled) {
        methods.push('paynet');
      }
    }

    // Check keshbek
    if (user?.balance && user.balance > 0) {
      methods.push('keshbek');
    }
    
    return methods.length > 0 ? methods : ['naqd'];
  }, [storeDetail, user?.balance]);

  useEffect(() => {
    if (availablePaymentMethods.length > 0 && !availablePaymentMethods.includes(paymentMethod)) {
      setPaymentMethod(availablePaymentMethods[0]);
    }
  }, [availablePaymentMethods, paymentMethod]);

  // Fetch store catalog for upsell recommendations
  useEffect(() => {
    if (items.length > 0) {
      const storeId = items[0].storeId;
      api.get(`/catalog?store_id=${storeId}`).then(res => {
        if (res.data?.success) {
          setStoreProducts(res.data.data.products || []);
        }
      }).catch(err => {
        console.error('Failed to fetch store products for upsell', err);
      });
    } else {
      setStoreProducts([]);
    }
  }, [items]);

  const upsellRecommendations = useMemo(() => {
    if (items.length === 0) return [];
    const inCartProductIds = items.map(item => item.productId);
    return storeProducts
      .filter(p => !inCartProductIds.includes(p.id) && p.variants && p.variants.length > 0)
      .slice(0, 3);
  }, [storeProducts, items]);

  const handleAddUpsellItem = (product: any) => {
    hapticFeedback('medium');
    if (product.variants && product.variants.length > 1) {
      setSelectedProductForVariant(product);
      return;
    }

    const variant = product.variants?.[0];
    const itemPrice = variant ? parseFloat(variant.price) : 0;

    const cartItem = {
      id: variant ? `${product.id}-${variant.uuid}` : `${product.id}`,
      productId: product.id,
      variantId: variant?.uuid,
      storeId: product.store_id.toString(),
      name: variant && product.variants && product.variants.length > 0 ? `${product.name} (${variant.name})` : product.name,
      price: itemPrice,
      quantity: 1,
      image: product.image_url
    };

    const storeInfo = {
      name: storeName,
      address: storeAddress || 'Asosiy filial'
    };

    addItem(cartItem, storeInfo);
  };

  const handleGetLocation = () => {
    if (!navigator.geolocation) {
      alert("Geolokatsiya brauzeringiz tomonidan qo'llab-quvvatlanmaydi.");
      return;
    }
    hapticFeedback('light');
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        try {
          // OpenStreetMap Nominatim reverse geocoding API
          const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`);
          const data = await res.json();
          const addressText = data.display_name || `📍 Lokatsiya (${latitude.toFixed(5)}, ${longitude.toFixed(5)})`;
          
          const newId = Date.now();
          addAddress({
            id: newId,
            title: "Mening joylashuvim",
            address: addressText,
            lat: latitude,
            lng: longitude,
            apartment: "",
            comment: "Avtomatik aniqlangan",
            is_default: true
          });
          setSelectedAddressId(newId);
          setShowAddressForm(false);
          hapticFeedback('success');
        } catch (err) {
          const addressText = `📍 Lokatsiya (${latitude.toFixed(5)}, ${longitude.toFixed(5)})`;
          const newId = Date.now();
          addAddress({
            id: newId,
            title: "Mening joylashuvim",
            address: addressText,
            lat: latitude,
            lng: longitude,
            apartment: "",
            comment: "Avtomatik aniqlangan",
            is_default: true
          });
          setSelectedAddressId(newId);
          setShowAddressForm(false);
          hapticFeedback('warning');
        } finally {
          setIsLocating(false);
        }
      },
      () => {
        setIsLocating(false);
        alert("Lokatsiyani aniqlab bo'lmadi. Ruxsat berilganini tekshiring.");
        hapticFeedback('error');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const handleSaveAddress = () => {
    if (!newAddress.address.trim()) return;
    hapticFeedback('medium');
    const newId = Date.now();
    addAddress({
      id: newId,
      title: newAddress.title,
      address: newAddress.address,
      lat: newAddress.lat,
      lng: newAddress.lng,
      apartment: newAddress.apartment,
      entrance: newAddress.entrance,
      floor: newAddress.floor,
      intercom: newAddress.intercom,
      comment: newAddress.comment,
      is_default: false
    });
    setSelectedAddressId(newId);
    setShowAddressForm(false);
    setNewAddress({
      title: 'Yangi manzil',
      address: '',
      lat: 40.7821,
      lng: 72.3442,
      apartment: '',
      entrance: '',
      floor: '',
      intercom: '',
      comment: ''
    });
  };

  const applyPromoCode = async (code: string) => {
    hapticFeedback('medium');
    if (!code.trim() || items.length === 0) return;
    
    try {
      const storeId = parseInt(items[0].storeId);
      const res = await api.post('/promo/validate', {
        code: code,
        store_id: storeId,
        subtotal: getSubtotal()
      });

      if (res.data?.success) {
        const promoData = res.data.data;
        setActivePromo({
          code: promoData.code,
          type: promoData.type,
          amount: promoData.amount,
          discountAmount: promoData.discount_amount,
          description: promoData.description
        });
        setPromoError('');
        setPromoCodeInput('');
      }
    } catch (err: any) {
      console.error(err);
      const errorMsg = err.response?.data?.message || "Promo-kod yaroqsiz yoki muddati tugagan";
      setPromoError(errorMsg);
      setActivePromo(null);
    }
  };

  const removePromoCode = () => {
    hapticFeedback('light');
    setActivePromo(null);
  };

  const subtotal = getSubtotal();
  const discountAmount = useMemo(() => {
    if (!activePromo) return 0;
    if (activePromo.type === 'percentage') {
      return subtotal * (activePromo.amount / 100);
    }
    return activePromo.amount || 0;
  }, [activePromo, subtotal]);

  const deliveryFee = deliveryMethod === 'delivery' ? storeDeliveryFee : 0;
  const total = Math.max(0, subtotal - discountAmount + deliveryFee);
  const minOrderLimit = storeDetail?.min_order !== undefined && storeDetail?.min_order !== null ? storeDetail.min_order : 0;
  const isMinOrderNotMet = subtotal < minOrderLimit;
  const selectedBranch = storeBranches.find(b => b.id === selectedBranchId) || storeBranches[0];

  useEffect(() => {
    if (paymentMethod === 'keshbek' && user?.balance !== undefined && user.balance < total) {
      const fallback = availablePaymentMethods.find(m => m !== 'keshbek') || 'naqd';
      setPaymentMethod(fallback);
    }
  }, [total, user?.balance, paymentMethod, availablePaymentMethods]);

  const handlePlaceOrder = async () => {
    if (items.length === 0) return;
    if (isMinOrderNotMet) {
      alert(`Minimal buyurtma summasi ${formatUzbekSum(minOrderLimit)}. Iltimos, yana taom qo'shing.`);
      return;
    }
    hapticFeedback('success');
    setIsOrdering(true);

    if (deliveryMethod === 'delivery' && !selectedAddressId) {
      alert("Iltimos, yetkazib berish manzilini tanlang yoki yangisini qo'shing!");
      setIsOrdering(false);
      return;
    }

    const selectedAddress = addresses.find(a => a.id === selectedAddressId);

    if (deliveryMethod === 'pickup' && !selectedBranch) {
      alert("Kechirasiz, restoranning faol filiali topilmadi.");
      setIsOrdering(false);
      return;
    }

    try {
      const payload = {
        store_id: parseInt(items[0].storeId),
        items: items.map(item => ({
          variant_id: item.variantId || item.id, // Must be UUID
          quantity: item.quantity
        })),
        type: deliveryMethod, // 'delivery' or 'pickup'
        payment_method: paymentMethod === 'naqd' ? 'cash' : paymentMethod,
        notes: deliveryNote.trim() ? deliveryNote : null,
        discount: discountAmount,

        // Conditional fields based on deliveryMethod
        branch_id: deliveryMethod === 'pickup' ? (selectedBranch ? selectedBranch.id : null) : null,
        address: deliveryMethod === 'delivery' ? (selectedAddress?.address || "") : null,
        latitude: deliveryMethod === 'delivery' ? (selectedAddress?.lat || 40.7821) : null,
        longitude: deliveryMethod === 'delivery' ? (selectedAddress?.lng || 72.3442) : null,
      };

      const res = await api.post('/orders', payload);
      if (res.data.success) {
        const orderData = res.data.data;
        setSuccessOrderNumber(orderData.order_number || orderData.order_uuid?.substring(0, 8) || `#${Math.floor(1000 + Math.random() * 9000)}`);
        
        // Handle redirect to click/payme URL if present
        const paymentUrl = orderData.payment_url;
        if (paymentUrl) {
          if (tg.openLink && typeof tg.openLink === 'function') {
            tg.openLink(paymentUrl);
          } else {
            window.open(paymentUrl, '_blank');
          }
        }

        setOrderSuccess(true);
        clearCart();
      }
    } catch (err: any) {
      console.error(err);
      const errorMsg = err.response?.data?.message || "Buyurtma berishda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.";
      alert(errorMsg);
    } finally {
      setIsOrdering(false);
    }
  };

  if (orderSuccess) {
    return (
      <div className="fixed inset-0 z-50 bg-amber-500 flex flex-col items-center justify-center p-6 text-white animate-fade-in">
        <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center mb-6 animate-scale-up shadow-xl shadow-amber-600">
          <span className="text-5xl">🎉</span>
        </div>
        <h2 className="text-3xl font-black text-center leading-tight mb-2">Buyurtma qabul qilindi!</h2>
        <p className="text-amber-100 font-bold text-center mb-8 bg-amber-600/30 px-4 py-2 rounded-2xl">
          Buyurtma raqami: <span className="text-white tracking-widest">{successOrderNumber}</span>
        </p>
        
        <div className="w-full bg-white text-slate-800 rounded-3xl p-5 shadow-2xl mb-8 relative overflow-hidden">
          <div className="flex items-center gap-4 mb-4">
            <div className="w-12 h-12 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center text-xl shrink-0">🛵</div>
            <div>
              <h3 className="text-sm font-black">Oshxona tayyorlamoqda</h3>
              <p className="text-[10px] text-slate-400 font-bold mt-0.5">Taxminiy yetkazib berish: 25-35 daqiqa</p>
            </div>
          </div>
          <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div className="h-full bg-amber-500 w-1/3 animate-pulse"></div>
          </div>
        </div>

        <button 
          onClick={() => {
            hapticFeedback('medium');
            navigate('/orders');
          }}
          className="w-full bg-white text-amber-600 py-4 rounded-2xl font-black text-sm hover:scale-[0.98] active:scale-95 transition-all shadow-lg"
        >
          Buyurtmani Kuzatish
        </button>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-slate-50 flex flex-col animate-fade-in">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b border-slate-100 px-4 py-4 flex items-center justify-between z-20 shadow-sm">
          <div className="flex items-center gap-3">
            <button 
              onClick={() => { hapticFeedback('light'); navigate(-1); }} 
              className="w-10 h-10 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 flex items-center justify-center active:scale-95 transition"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <h1 className="text-sm font-black text-slate-800 leading-none">Savat</h1>
              <p className="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider">0 ta mahsulot</p>
            </div>
          </div>
        </div>
        <div className="p-8 text-center flex flex-col items-center justify-center flex-1">
          <div className="w-20 h-20 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-4">
            <ShoppingBag className="w-8 h-8" />
          </div>
          <h3 className="text-sm font-black text-slate-800">Savatingiz hozircha bo'sh</h3>
          <p className="text-xs text-slate-400 mt-1.5 max-w-[250px] leading-relaxed font-semibold">
            Siz hali savatga hech narsa qo'shmadingiz. Mazali taomlardan tanlab, bu yerga qo'shing.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col animate-fade-in">
      {/* Header */}
      <div className="sticky top-0 bg-white border-b border-slate-100 px-4 py-4 flex items-center justify-between z-20 shadow-sm">
        <div className="flex items-center gap-3">
          <button 
            onClick={() => { hapticFeedback('light'); navigate(-1); }} 
            className="w-10 h-10 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 flex items-center justify-center active:scale-95 transition"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className="text-sm font-black text-slate-800 leading-none">Savat</h1>
            <p className="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider">{items.length} ta mahsulot</p>
          </div>
        </div>
      </div>

      <div className="p-4 space-y-4 pb-28 flex-1 overflow-y-auto">
        {/* Delivery Method Selector */}
        <div className="bg-white p-2 rounded-2xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] flex gap-1">
          <button 
            onClick={() => { hapticFeedback('light'); setDeliveryMethod('delivery'); }}
            className={`flex-1 py-2.5 text-xs font-black rounded-xl transition ${deliveryMethod === 'delivery' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'}`}
          >
            🛵 Yetkazib berish
          </button>
          <button 
            onClick={() => { hapticFeedback('light'); setDeliveryMethod('pickup'); }}
            className={`flex-1 py-2.5 text-xs font-black rounded-xl transition ${deliveryMethod === 'pickup' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'}`}
          >
            🏃 Olib ketish
          </button>
        </div>

      {/* Checkout Address Block (Only for delivery) */}
      {deliveryMethod === 'delivery' ? (
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3">
        <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">Yetkazib berish manzili</h3>
        
        {showAddressForm ? (
          <div className="bg-slate-50 p-4 rounded-2xl border border-amber-500/20 flex flex-col gap-3 animate-fade-in">
            <button
              type="button"
              onClick={handleGetLocation}
              disabled={isLocating}
              className="w-full py-3 bg-amber-500/10 text-amber-600 rounded-xl text-xs font-black flex items-center justify-center gap-2 border border-amber-500/20 hover:bg-amber-500/20 active:scale-95 transition-all disabled:opacity-50"
            >
              {isLocating ? (
                <span className="w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></span>
              ) : (
                <span>📍</span>
              )}
              <span>{isLocating ? "Lokatsiya olinmoqda..." : "Hozirgi joylashuvni aniqlash"}</span>
            </button>
            <input 
              type="text" 
              placeholder="Ko'cha va uy raqami (masalan: Chilonzor 12)"
              value={newAddress.address}
              onChange={e => setNewAddress({...newAddress, address: e.target.value})}
              className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors font-semibold"
            />
            <div className="grid grid-cols-2 gap-3">
              <input 
                type="text" placeholder="Xonadon"
                value={newAddress.apartment} onChange={e => setNewAddress({...newAddress, apartment: e.target.value})}
                className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors"
              />
              <input 
                type="text" placeholder="Qavat"
                value={newAddress.floor} onChange={e => setNewAddress({...newAddress, floor: e.target.value})}
                className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors"
              />
            </div>
            <div className="flex gap-2 pt-2">
              <button 
                onClick={() => setShowAddressForm(false)}
                className="flex-1 py-3 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs active:scale-95 transition"
              >
                Bekor qilish
              </button>
              <button 
                onClick={handleSaveAddress}
                className="flex-1 py-3 bg-amber-500 text-white font-bold rounded-xl text-xs active:scale-95 transition"
              >
                Saqlash
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-2">
            {addresses.map((addr) => (
              <div 
                key={addr.id}
                onClick={() => {
                  hapticFeedback('light');
                  setSelectedAddressId(addr.id);
                }}
                className={`p-3 rounded-2xl border transition flex items-start gap-3 cursor-pointer ${
                  selectedAddressId === addr.id ? 'border-amber-500 bg-amber-50/20' : 'border-slate-100 bg-white hover:bg-slate-50'
                }`}
              >
                <span className="text-base mt-0.5">🏠</span>
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-black text-slate-800 flex items-center gap-1.5">
                    {addr.title}
                    {addr.is_default && <span className="text-[8px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-400">Asosiy</span>}
                  </p>
                  <p className="text-[10px] text-slate-400 font-medium truncate mt-0.5">{addr.address}</p>
                </div>
                {selectedAddressId === addr.id && <CheckCircle2 className="w-4 h-4 text-amber-500 mt-1" />}
              </div>
            ))}
            <button 
              onClick={() => { hapticFeedback('medium'); setShowAddressForm(true); }}
              className="flex items-center justify-center gap-2 w-full p-3 border border-dashed border-slate-300 rounded-2xl text-slate-500 font-bold text-xs hover:bg-slate-50 transition active:scale-95"
            >
              <PlusCircle className="w-4 h-4" />
              Yangi manzil qo'shish
            </button>
          </div>
        )}
      </div>
      ) : (
        <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-2">
          <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">Olib ketish joyi</h3>
          
          {storeBranches.length > 1 && (
            <div className="mb-2">
              <label className="text-[10px] text-slate-400 font-bold block mb-1 font-sans">Filialni tanlang:</label>
              <select
                value={selectedBranchId || ""}
                onChange={(e) => {
                  const id = parseInt(e.target.value);
                  setSelectedBranchId(id);
                  hapticFeedback('light');
                }}
                className="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-amber-500 font-sans"
              >
                {storeBranches.map((branch: any) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name}
                  </option>
                ))}
              </select>
            </div>
          )}

          {selectedBranch ? (
            <div className="p-3 bg-amber-50/50 border border-amber-100 rounded-2xl flex items-start gap-3">
              <span className="text-xl mt-0.5">🏪</span>
              <div>
                <p className="text-xs font-black text-slate-800">{selectedBranch.name || storeName || "Siz tanlagan oshxona"}</p>
                <p className="text-[10px] text-slate-500 font-medium mt-0.5">{selectedBranch.address || storeAddress || "Filial manzili ko'rsatilmagan"}</p>
                {selectedBranch.phone && (
                  <p className="text-[9px] text-slate-400 font-bold mt-1">📞 {selectedBranch.phone}</p>
                )}
              </div>
            </div>
          ) : (
            <p className="text-xs text-rose-500 font-bold">Faol filiallar topilmadi</p>
          )}
        </div>
      )}

      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-4">
        <div className="flex justify-between items-center border-b border-slate-100 pb-3">
          <div>
            <h2 className="text-base font-black text-slate-800">Mening Savatim</h2>
            <p className="text-[10px] text-amber-600 font-bold mt-0.5">{items.length} ta mahsulot</p>
          </div>
          <button onClick={clearCart} className="text-[10px] font-black text-red-500 bg-red-50 px-3 py-1.5 rounded-xl hover:bg-red-100 transition flex items-center gap-1 active:scale-95">
            <Trash2 className="w-3.5 h-3.5" /> Tozalash
          </button>
        </div>

        {/* Cart Item Row List */}
        <div className="space-y-3">
          {items.map((item) => {
            return (
              <div key={item.id} className="flex items-center gap-3 bg-slate-50/50 p-2.5 rounded-2xl border border-slate-100">
                <div className="w-12 h-12 rounded-xl overflow-hidden shrink-0 bg-white flex items-center justify-center border border-slate-100">
                  {item.image ? (
                    <img src={item.image} alt="" className="w-full h-full object-cover" />
                  ) : (
                    <span className="text-lg">🍔</span>
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <h4 className="text-xs font-black text-slate-800 truncate">{item.name}</h4>
                  <span className="text-[11px] font-black text-slate-800 block mt-1">{formatUzbekSum(item.price * item.quantity)}</span>
                </div>
                <div className="flex items-center bg-white border border-slate-200 rounded-full p-1 shrink-0 scale-90">
                  <button 
                    onClick={() => {
                      hapticFeedback('medium');
                      if (item.quantity <= 1) {
                        removeItem(item.id);
                      } else {
                        updateQuantity(item.id, item.quantity - 1);
                      }
                    }} 
                    className="w-6 h-6 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 transition"
                  >
                    <Minus className="w-3 h-3" />
                  </button>
                  <span className="px-2 text-xs font-black text-slate-800 w-6 text-center">{item.quantity}</span>
                  <button 
                    onClick={() => {
                      hapticFeedback('light');
                      updateQuantity(item.id, item.quantity + 1);
                    }} 
                    className="w-6 h-6 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 transition"
                  >
                    <Plus className="w-3 h-3" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Dynamic Upsell / Recommendations Card */}
      {upsellRecommendations.length > 0 && (
        <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3">
          <div className="flex justify-between items-center">
            <h3 className="text-[9px] font-black uppercase tracking-widest text-amber-600">
              Buyurtmaga qo'shishni unutmadingizmi? 🍰🥤
            </h3>
          </div>
          <div className="grid grid-cols-3 gap-2.5">
            {upsellRecommendations.map((product) => {
              const basePrice = getProductBasePrice(product);
              return (
                <div key={product.id} className="bg-slate-50/50 p-2 rounded-2xl border border-slate-100 flex flex-col justify-between text-center min-w-0">
                  <div className="w-full aspect-square rounded-xl overflow-hidden bg-white flex items-center justify-center border border-slate-100">
                    {product.image_url ? (
                      <img src={product.image_url} alt="" className="w-full h-full object-cover" />
                    ) : (
                      <span className="text-xl">🍪</span>
                    )}
                  </div>
                  <div className="mt-2 flex-1 flex flex-col justify-between">
                    <div>
                      <h4 className="text-[10px] font-black text-slate-800 line-clamp-1 leading-tight">{product.name}</h4>
                      <p className="text-[9px] font-black text-slate-800 mt-1">{formatUzbekSum(basePrice)}</p>
                    </div>
                    <button
                      onClick={() => handleAddUpsellItem(product)}
                      className="mt-2 w-full py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-black rounded-xl text-[9px] transition active:scale-95 cursor-pointer"
                    >
                      + Qo'shish
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Interactive Promo-code validator card */}
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3">
        <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">Promo-kod qo'llash</h3>
        {activePromo ? (
          <div className="p-3 bg-amber-50 border border-amber-200 rounded-2xl flex justify-between items-center animate-fade-in">
            <div>
              <p className="text-xs font-black text-amber-700">{activePromo.code} faol!</p>
              <p className="text-[10px] text-amber-600 mt-0.5">{activePromo.description}</p>
            </div>
            <button 
              onClick={removePromoCode}
              className="text-xs text-rose-500 font-bold bg-white px-3 py-1.5 rounded-xl border border-rose-100 active:scale-95 transition"
            >
              Bekor qilish
            </button>
          </div>
        ) : (
          <div className="flex gap-2">
            <input 
              type="text"
              value={promoCodeInput}
              onChange={(e) => setPromoCodeInput(e.target.value.toUpperCase())}
              placeholder="PROMOKODNI KIRITING"
              className="flex-1 text-xs bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 font-bold uppercase transition text-slate-800"
            />
            <button 
              onClick={() => applyPromoCode(promoCodeInput)}
              className="bg-amber-500 hover:bg-amber-600 text-white font-black text-xs px-5 rounded-2xl active:scale-95 transition"
            >
              Qo'llash
            </button>
          </div>
        )}
        {promoError && <p className="text-[9px] text-rose-500 font-bold mt-1 pl-1">⚠️ {promoError}</p>}
      </div>

      {/* Payment Picker Selection */}
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3">
        <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">To'lov Turi</h3>
        <div className="grid grid-cols-3 gap-2">
          {availablePaymentMethods.map((method) => {
            const icon = method === 'click' ? '🔵' : method === 'payme' ? '🟢' : method === 'uzum' ? '🟣' : method === 'paynet' ? '🔴' : method === 'keshbek' ? '✨' : '💵';
            const label = method === 'naqd' ? 'Naqd' : method === 'uzum' ? 'Uzum' : method === 'paynet' ? 'Paynet' : method === 'keshbek' ? 'Keshbek' : method.toUpperCase();
            const isDisabled = method === 'keshbek' && user?.balance !== undefined && user.balance < total;
            return (
              <button
                key={method}
                disabled={isDisabled}
                onClick={() => {
                  hapticFeedback('light');
                  setPaymentMethod(method);
                }}
                className={`py-3 px-1 rounded-2xl border text-[10px] font-black flex flex-col items-center justify-center gap-1 transition active:scale-95 duration-200 ${
                  isDisabled ? 'opacity-40 cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400' :
                  paymentMethod === method ? 'border-amber-500 bg-amber-50 text-amber-700 shadow-sm' : 'border-slate-100 bg-white text-slate-500'
                }`}
              >
                <span className="text-base leading-none">{icon}</span>
                <span className="uppercase tracking-wider">{label}</span>
                {method === 'keshbek' && user?.balance !== undefined && (
                  <span className="text-[8px] text-slate-400 font-bold lowercase">({formatUzbekSum(user.balance)})</span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* Customizable Driver delivery instruction note */}
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-2">
        <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">Izoh yozish (Kuryer uchun)</h3>
        <input 
          type="text" 
          placeholder="Uy raqami, podyezd, dom kodi yoki xohishlaringiz..."
          value={deliveryNote}
          onChange={(e) => setDeliveryNote(e.target.value)}
          className="w-full text-xs bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 font-semibold transition text-slate-800"
        />
      </div>

      {/* Final calculated aggregate cost overview */}
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-4">
        {isMinOrderNotMet && (
          <div className="bg-rose-50 border border-rose-100 p-3.5 rounded-2xl text-[10px] font-bold text-rose-600 flex items-start gap-2.5 animate-pulse">
            <span className="text-sm">⚠️</span>
            <div>
              <p className="font-black">Minimal buyurtma miqdori yetarli emas!</p>
              <p className="text-[9px] text-rose-500 font-semibold mt-0.5">Ushbu restorandan buyurtma berish uchun kamida {formatUzbekSum(minOrderLimit)} lik mahsulot qo'shishingiz kerak (Hozirgi summa: {formatUzbekSum(subtotal)}).</p>
            </div>
          </div>
        )}
        <div className="space-y-2 text-xs font-semibold">
          <div className="flex justify-between text-slate-500">
            <span>Taomlar qiymati</span>
            <span className="font-black text-slate-800">{formatUzbekSum(subtotal)}</span>
          </div>
          {activePromo && (
            <div className="flex justify-between text-amber-600">
              <span>Promo-kod chegirmasi ({activePromo.amount}{activePromo.type === 'percentage' ? '%' : ' so\'m'})</span>
              <span className="font-black">- {formatUzbekSum(discountAmount)}</span>
            </div>
          )}
          {deliveryMethod === 'delivery' && (
            <div className="flex justify-between text-slate-500">
              <span>Kuryer xizmati</span>
              <span className="font-black text-slate-800">{formatUzbekSum(deliveryFee)}</span>
            </div>
          )}
          <div className="pt-2 border-t border-slate-100 flex justify-between text-sm font-black text-slate-800">
            <span>Jami</span>
            <span className="text-amber-500 text-base">{formatUzbekSum(total)}</span>
          </div>
        </div>
        
        <button 
          onClick={handlePlaceOrder}
          disabled={isOrdering || (deliveryMethod === 'delivery' && !selectedAddressId) || isMinOrderNotMet}
          className={`w-full py-4 rounded-2xl font-black text-sm transition-all shadow-lg flex items-center justify-center gap-2 ${
            isOrdering || (deliveryMethod === 'delivery' && !selectedAddressId) || isMinOrderNotMet ? 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none' : 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-500/30 active:scale-95 hover:shadow-xl'
          }`}
        >
          {isOrdering ? (
            <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          ) : (
            <>Buyurtma berish - {formatUzbekSum(total)}</>
          )}
        </button>
        {(deliveryMethod === 'delivery' && !selectedAddressId) && (
          <p className="text-[10px] text-center text-rose-500 font-bold mt-2">Iltimos, yetkazib berish manzilini tanlang</p>
        )}
      </div>

      {/* Product Variant / Option Selector Sheet */}
      <VariantSelectorModal
        isOpen={!!selectedProductForVariant}
        onClose={() => setSelectedProductForVariant(null)}
        product={selectedProductForVariant}
        onAdd={(variant) => {
          const cartItem = {
            id: `${selectedProductForVariant.id}-${variant.uuid}`,
            productId: selectedProductForVariant.id,
            variantId: variant.uuid,
            storeId: selectedProductForVariant.store_id.toString(),
            name: `${selectedProductForVariant.name} (${variant.name})`,
            price: parseFloat(variant.price as string),
            quantity: 1,
            image: selectedProductForVariant.image_url
          };

          const storeInfo = {
            name: storeName,
            address: storeAddress || 'Asosiy filial'
          };

          addItem(cartItem, storeInfo);
        }}
      />
      </div>
    </div>
  );
}
