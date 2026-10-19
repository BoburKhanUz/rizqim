import { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { isAxiosError } from 'axios';
import { logApiError } from '../lib/apiError';
import { useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import type { Address } from '../store/useAddressStore';
import { hapticFeedback, tg } from '../lib/telegram';
import api from '../services/api';
import { Trash2, Minus, Plus, ShoppingBag, CheckCircle2, PlusCircle, ArrowLeft, Pencil } from 'lucide-react';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { useAuthStore } from '../store/authStore';
import { getAddBlockedMessage, getVariantStock, isProductSoldOut } from '../lib/stock';
import type { StockVariant } from '../lib/stock';
import { getAllowedPaymentMethods, getAvailableOrderTypes, pickOrderType, pickPaymentMethod } from '../lib/checkout';
import type { CartOrderType, CartPaymentMethod } from '../lib/checkout';
import { formatUzbekSum } from '../lib/format';
import { parseAmount } from '../lib/money';
import { buildCartItem, getProductBasePrice, getStoreInfo, parsePrice } from '../lib/catalog';
import AddressLocationField from '../features/address/AddressLocationField';
import { isOnAnyBranch, toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue, LatLng } from '../features/address/types';
import type { ApiErrorBody, ApiResponse, BranchSummary, Product, PromoValidation, StoreCatalog, StoreListItem } from '../types';

interface AppliedPromo {
  // The code string is what we send as `promo_code` when placing the order
  code: string;
  type: string;
  amount: number;
  // Discount the server computed for `validatedSubtotal` (older backends may not send it)
  discountAmount: number | null;
  validatedSubtotal: number;
  description?: string;
}

interface OrderResponseData {
  order_number?: string | number | null;
  order_uuid?: string | null;
  uuid?: string | null;
  payment_url?: string | null;
}

const ORDER_TYPE_TABS: ReadonlyArray<{ type: CartOrderType; label: string }> = [
  { type: 'delivery', label: '🛵 Yetkazib berish' },
  { type: 'pickup', label: '🏃 Olib ketish' },
];

// The address form of the cart: the location (text + pin) plus the few extra fields it asks for
interface AddressDraft extends AddressLocationValue {
  title: string;
  apartment: string;
  floor: string;
}

const EMPTY_ADDRESS_DRAFT: AddressDraft = {
  title: 'Yangi manzil',
  address: '',
  lat: null,
  lng: null,
  apartment: '',
  floor: '',
};

// Never invent an order number: use what the server sent, else the start of the order uuid, else nothing.
function getOrderDisplayNumber(order: OrderResponseData): string {
  if (order.order_number !== undefined && order.order_number !== null && String(order.order_number).trim() !== '') {
    return String(order.order_number);
  }
  const uuid = order.order_uuid || order.uuid;
  return uuid ? uuid.substring(0, 8) : '';
}

export default function Cart() {
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { items, storeName, storeAddress, updateQuantity, removeItem, clearCart, getSubtotal, addItem } = useCartStore();
  const { addresses, selectedAddressId, setSelectedAddressId, addAddress, updateAddress } = useAddressStore();
  const balance = user?.balance;

  const [deliveryNote, setDeliveryNote] = useState('');
  // The customer's own pick; null until they pick one (the method actually used is effectivePaymentMethod)
  const [chosenPaymentMethod, setChosenPaymentMethod] = useState<CartPaymentMethod | null>(null);
  const [promoCodeInput, setPromoCodeInput] = useState('');
  const [activePromo, setActivePromo] = useState<AppliedPromo | null>(null);
  const [promoError, setPromoError] = useState('');

  // The upsell catalog of the store it was fetched for, so a different store's products are never shown
  const [catalog, setCatalog] = useState<{ storeId: string; products: Product[] } | null>(null);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<Product | null>(null);

  // The customer's own pick (the way actually used is deliveryMethod, which only ever is one the store takes)
  const [chosenOrderType, setChosenOrderType] = useState<CartOrderType>('delivery');
  const [storeBranches, setStoreBranches] = useState<BranchSummary[]>([]);
  // Every branch of every store: a saved address sitting exactly on one of them is a leftover of the old cart
  // copying the restaurant's coordinates into hand-typed addresses (see isOnAnyBranch)
  const [branchPoints, setBranchPoints] = useState<LatLng[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [storeDeliveryFee, setStoreDeliveryFee] = useState(15000);

  const [storeDetail, setStoreDetail] = useState<StoreListItem | null>(null);

  const [showAddressForm, setShowAddressForm] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState<number | null>(null);
  const [addressDraft, setAddressDraft] = useState<AddressDraft>(EMPTY_ADDRESS_DRAFT);
  const patchAddressDraft = useCallback((patch: Partial<AddressDraft>) => {
    setAddressDraft((prev) => ({ ...prev, ...patch }));
  }, []);

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
      api.get<ApiResponse<StoreListItem[]>>('/stores').then(res => {
        if (res.data?.success) {
          const stores = res.data.data;
          setBranchPoints(stores.flatMap(s => (s.branches ?? []).flatMap(b => toLatLng(b.latitude, b.longitude) ?? [])));

          const matchedStore = stores.find(s => s.id.toString() === storeId);
          if (matchedStore) {
            setStoreDetail(matchedStore);
            if (matchedStore.branches) {
              setStoreBranches(matchedStore.branches);
              // Keep the branch the customer picked for pickup; only fall back to the first one
              setSelectedBranchId(current =>
                matchedStore.branches.some(b => b.id === current) ? current : (matchedStore.branches[0]?.id ?? null)
              );
            }
            const rawFee = matchedStore.delivery_fee !== undefined && matchedStore.delivery_fee !== null
              ? matchedStore.delivery_fee
              : (matchedStore.payment_settings?.delivery_fee !== undefined ? parsePrice(matchedStore.payment_settings.delivery_fee) : NaN);
            setStoreDeliveryFee(isNaN(rawFee) ? 15000 : rawFee);
          }
        }
      }).catch(err => {
        logApiError('Failed to fetch store branches', err);
      });
    }
  }, [items]);

  // What the store takes at checkout, by the rules of the server. Until the store has loaded its settings are read as
  // "nothing set up" (cash, delivery and pickup), which is what the server assumes for a store without settings.
  const availablePaymentMethods = useMemo<CartPaymentMethod[]>(
    () => getAllowedPaymentMethods(storeDetail?.payment_settings, balance),
    [storeDetail, balance]
  );
  const availableOrderTypes = useMemo<CartOrderType[]>(
    () => getAvailableOrderTypes(storeDetail?.payment_settings),
    [storeDetail]
  );

  // Fetch store catalog for upsell recommendations
  useEffect(() => {
    if (items.length > 0) {
      const storeId = items[0].storeId;
      api.get<ApiResponse<StoreCatalog>>(`/catalog?store_id=${storeId}`).then(res => {
        if (res.data?.success) {
          setCatalog({ storeId, products: res.data.data.products || [] });
        }
      }).catch(err => {
        logApiError('Failed to fetch store products for upsell', err);
      });
    }
  }, [items]);

  const cartStoreId = items[0]?.storeId;
  const storeProducts = useMemo<Product[]>(
    () => (catalog && catalog.storeId === cartStoreId ? catalog.products : []),
    [catalog, cartStoreId]
  );

  const upsellRecommendations = useMemo(() => {
    if (items.length === 0) return [];
    const inCartProductIds = items.map(item => item.productId);
    return storeProducts
      .filter(p => !inCartProductIds.includes(p.id) && p.variants && p.variants.length > 0 && !isProductSoldOut(p))
      .slice(0, 3);
  }, [storeProducts, items]);

  // Cart items carry no stock info: look the variant up in the store catalog fetched above.
  const findCatalogVariant = (productId: number, variantId?: string) =>
    storeProducts.find(p => p.id === productId)?.variants?.find(v => v.uuid === variantId);

  // Same stock rule as the store page: never add a sold-out variant or more than what is in stock.
  const canAddMore = (variant: StockVariant | undefined, cartItemId: string): boolean => {
    const inCart = items.find(item => item.id === cartItemId)?.quantity ?? 0;
    const blocked = getAddBlockedMessage(variant, inCart);
    if (blocked) {
      hapticFeedback('error');
      alert(blocked);
      return false;
    }
    return true;
  };

  const handleAddUpsellItem = (product: Product) => {
    hapticFeedback('medium');
    if (product.variants && product.variants.length > 1) {
      setSelectedProductForVariant(product);
      return;
    }

    const variant = product.variants?.[0];
    const cartItem = buildCartItem(product, variant);

    if (!canAddMore(variant, cartItem.id)) return;

    addItem(cartItem, getStoreInfo({ name: storeName, address: storeAddress }));
  };

  // The map point of a saved address, or null when it has none that can be trusted
  const getAddressPoint = (address: Address): LatLng | null => {
    const point = toLatLng(address.lat, address.lng);
    return point && !isOnAnyBranch(point, branchPoints) ? point : null;
  };

  const closeAddressForm = () => {
    setShowAddressForm(false);
    setEditingAddressId(null);
    setAddressDraft(EMPTY_ADDRESS_DRAFT);
  };

  const openNewAddressForm = () => {
    hapticFeedback('medium');
    setEditingAddressId(null);
    setAddressDraft(EMPTY_ADDRESS_DRAFT);
    setShowAddressForm(true);
  };

  // Editing is also how an address without a (trustworthy) map point gets one: the pin starts empty for it
  const openEditAddressForm = (address: Address) => {
    hapticFeedback('light');
    const point = getAddressPoint(address);
    setEditingAddressId(address.id);
    setAddressDraft({
      title: address.title,
      address: address.address,
      lat: point?.lat ?? null,
      lng: point?.lng ?? null,
      apartment: address.apartment ?? '',
      floor: address.floor ?? '',
    });
    setShowAddressForm(true);
  };

  const draftPoint = toLatLng(addressDraft.lat, addressDraft.lng);
  const canSaveAddress = addressDraft.address.trim() !== '' && draftPoint !== null;

  const handleSaveAddress = () => {
    if (!canSaveAddress || draftPoint === null) return;
    hapticFeedback('medium');

    const fields = {
      title: addressDraft.title,
      address: addressDraft.address.trim(),
      lat: draftPoint.lat,
      lng: draftPoint.lng,
      apartment: addressDraft.apartment,
      floor: addressDraft.floor,
    };

    if (editingAddressId !== null) {
      updateAddress(editingAddressId, fields);
      setSelectedAddressId(editingAddressId);
    } else {
      const newId = Date.now();
      addAddress({ id: newId, ...fields, is_default: false });
      setSelectedAddressId(newId);
    }
    closeAddressForm();
  };

  const applyPromoCode = async (code: string) => {
    hapticFeedback('medium');
    if (!code.trim() || items.length === 0) return;

    try {
      const storeId = parseInt(items[0].storeId);
      const validatedSubtotal = getSubtotal();
      const res = await api.post<ApiResponse<PromoValidation>>('/promo/validate', {
        code: code,
        store_id: storeId,
        subtotal: validatedSubtotal
      });

      if (res.data?.success) {
        const promoData = res.data.data;
        setActivePromo({
          // Keep the code string: the order is sent with `promo_code` and the server recomputes the discount
          code: typeof promoData.code === 'string' && promoData.code.trim() ? promoData.code.trim() : code.trim(),
          type: String(promoData.type ?? ''),
          amount: parseAmount(promoData.amount) ?? 0,
          discountAmount: parseAmount(promoData.discount_amount),
          validatedSubtotal,
          description: promoData.description
        });
        setPromoError('');
        setPromoCodeInput('');
      }
    } catch (err) {
      logApiError('Failed to apply promo code:', err);
      const errorMsg = (isAxiosError<ApiErrorBody>(err) && err.response?.data?.message) || "Promo-kod yaroqsiz yoki muddati tugagan";
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
    if (!activePromo || subtotal <= 0) return 0;

    let discount: number;
    if (activePromo.discountAmount !== null && activePromo.validatedSubtotal === subtotal) {
      // Prefer the server's figure (it knows caps like max_discount_amount). It is only valid for the
      // subtotal it was computed for, so once the cart changes we fall back to the local estimate.
      discount = activePromo.discountAmount;
    } else if (activePromo.type === 'percentage') {
      discount = Math.round(subtotal * (activePromo.amount / 100));
    } else {
      discount = Math.round(activePromo.amount);
    }
    // The preview can never exceed the cart subtotal
    return Math.min(Math.max(discount, 0), subtotal);
  }, [activePromo, subtotal]);

  // How the order is placed: the customer's pick while the store takes it, else the other way. Null when the store
  // takes neither, and then no order can be placed.
  const deliveryMethod = pickOrderType(availableOrderTypes, chosenOrderType);
  const deliveryFee = deliveryMethod === 'delivery' ? storeDeliveryFee : 0;
  const total = Math.max(0, subtotal - discountAmount + deliveryFee);
  const minOrderLimit = storeDetail?.min_order !== undefined && storeDetail?.min_order !== null ? storeDetail.min_order : 0;
  const isMinOrderNotMet = subtotal < minOrderLimit;
  // The server decides whether the store can take orders (debt / finance status): `is_order_locked` on the
  // public store. A missing field (older backend) means not locked.
  const isStoreLocked = storeDetail?.is_order_locked === true;
  const selectedBranch = storeBranches.find(b => b.id === selectedBranchId) || storeBranches[0];
  // The map opens at the restaurant until the customer has dropped a pin (a view only, never saved as an address)
  const mapFallbackCenter = toLatLng(selectedBranch?.latitude, selectedBranch?.longitude);

  // Cashback pays for the whole order or not at all; a choice that is no longer possible (store settings changed,
  // cart grew past the balance) quietly becomes the first method that is. Null when nothing can be paid with.
  const isKeshbekAffordable = balance !== undefined && balance >= total;
  const effectivePaymentMethod = pickPaymentMethod(availablePaymentMethods, chosenPaymentMethod, isKeshbekAffordable);

  // Delivery orders are routed to a branch (and priced) by their coordinates, so we need real ones.
  const selectedAddress = addresses.find(a => a.id === selectedAddressId);
  const deliveryPoint = selectedAddress ? getAddressPoint(selectedAddress) : null;
  const hasDeliveryLocation = deliveryPoint !== null;
  const isOrderDisabled = isOrdering || isMinOrderNotMet || isStoreLocked
    || deliveryMethod === null || effectivePaymentMethod === null
    || (deliveryMethod === 'delivery' && !hasDeliveryLocation);

  const handlePlaceOrder = async () => {
    if (items.length === 0) return;
    if (isStoreLocked) {
      hapticFeedback('error');
      alert("Do'kon vaqtincha to'lov muammolari sababli buyurtma qabul qilmayapti. Savat saqlanadi, keyinroq urinib ko'ring.");
      return;
    }
    if (isMinOrderNotMet) {
      alert(`Minimal buyurtma summasi ${formatUzbekSum(minOrderLimit)}. Iltimos, yana taom qo'shing.`);
      return;
    }
    // The server refuses an order its store does not take this way or with this payment
    if (deliveryMethod === null) {
      hapticFeedback('error');
      alert("Do'kon hozircha yetkazib berishni ham, olib ketishni ham qabul qilmayapti. Keyinroq urinib ko'ring.");
      return;
    }
    if (effectivePaymentMethod === null) {
      hapticFeedback('error');
      alert("Do'kon hozircha to'lov qabul qilmayapti yoki keshbek balansi yetarli emas. Keyinroq urinib ko'ring.");
      return;
    }
    hapticFeedback('success');
    setIsOrdering(true);

    if (deliveryMethod === 'delivery' && !selectedAddress) {
      alert("Iltimos, yetkazib berish manzilini tanlang yoki yangisini qo'shing!");
      setIsOrdering(false);
      return;
    }

    // Never send made-up coordinates: they silently pick the wrong branch and delivery fee.
    if (deliveryMethod === 'delivery' && !deliveryPoint) {
      hapticFeedback('error');
      alert("Tanlangan manzilning xaritadagi joylashuvi aniqlanmagan. Iltimos, manzilni tahrirlab, xaritada belgilang.");
      setIsOrdering(false);
      return;
    }

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
        payment_method: effectivePaymentMethod === 'naqd' ? 'cash' : effectivePaymentMethod,
        notes: deliveryNote.trim() ? deliveryNote : null,
        // The server computes the discount itself from the promo code
        promo_code: activePromo?.code ?? null,

        // Conditional fields based on deliveryMethod
        branch_id: deliveryMethod === 'pickup' ? (selectedBranch ? selectedBranch.id : null) : null,
        address: deliveryMethod === 'delivery' ? (selectedAddress?.address || "") : null,
        latitude: deliveryMethod === 'delivery' ? deliveryPoint?.lat ?? null : null,
        longitude: deliveryMethod === 'delivery' ? deliveryPoint?.lng ?? null : null,
      };

      const res = await api.post<ApiResponse<OrderResponseData>>('/orders', payload);
      if (res.data.success) {
        const orderData = res.data.data ?? {};
        setSuccessOrderNumber(getOrderDisplayNumber(orderData));

        // Handle redirect to click/payme URL if present
        const paymentUrl = orderData.payment_url;
        if (paymentUrl) {
          if (typeof tg.openLink === 'function') {
            tg.openLink(paymentUrl);
          } else {
            window.open(paymentUrl, '_blank');
          }
        }

        setOrderSuccess(true);
        clearCart();
      }
    } catch (err) {
      logApiError('Failed to place order:', err);
      const response = isAxiosError<ApiErrorBody>(err) ? err.response : undefined;
      const promoErrors = response?.data?.errors?.promo_code;
      if (response?.status === 422 && promoErrors) {
        // The server no longer accepts the promo: drop it and make the user re-check the total.
        const promoMessage = (Array.isArray(promoErrors) ? promoErrors[0] : promoErrors) || response.data?.message || "Promo-kod yaroqsiz yoki muddati tugagan";
        setActivePromo(null);
        setPromoError(promoMessage);
        hapticFeedback('error');
        alert(`${promoMessage}\n\nPromo-kod olib tashlandi. Iltimos, jami summani qayta tekshirib, buyurtmani qaytadan tasdiqlang.`);
      } else {
        const errorMsg = response?.data?.message || "Buyurtma berishda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.";
        alert(errorMsg);
      }
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
        <h2 className={`text-3xl font-black text-center leading-tight ${successOrderNumber ? 'mb-2' : 'mb-8'}`}>Buyurtma qabul qilindi!</h2>
        {successOrderNumber && (
          <p className="text-amber-100 font-bold text-center mb-8 bg-amber-600/30 px-4 py-2 rounded-2xl">
            Buyurtma raqami: <span className="text-white tracking-widest">{successOrderNumber}</span>
          </p>
        )}
        
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
        {isStoreLocked && (
          <div className="bg-rose-50 border border-rose-100 p-3.5 rounded-2xl text-[10px] font-bold text-rose-600 flex items-start gap-2.5 animate-pulse">
            <span className="text-sm">⚠️</span>
            <div>
              <p className="font-black">DO'KON VAQTINCHA BLOKLANGAN</p>
              <p className="text-[9px] text-rose-500 font-semibold mt-0.5 leading-relaxed">Do'kon vaqtincha to'lov muammolari (qarz limitidan oshganlik) sababli buyurtma qabul qilmayapti. Savat ma'lumotlarini saqlab, keyinroq urinib ko'ring.</p>
            </div>
          </div>
        )}

        {/* Delivery Method Selector: a way the store switched off stays in sight, but can't be picked */}
        <div role="group" aria-label="Buyurtma turi" className="bg-white p-2 rounded-2xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] flex gap-1">
          {ORDER_TYPE_TABS.map(({ type, label }) => {
            const isOffered = availableOrderTypes.includes(type);
            return (
              <button
                key={type}
                type="button"
                disabled={!isOffered}
                aria-pressed={deliveryMethod === type}
                onClick={() => { hapticFeedback('light'); setChosenOrderType(type); }}
                className={`flex-1 py-2.5 text-xs font-black rounded-xl transition ${
                  !isOffered ? 'text-slate-300 cursor-not-allowed'
                    : deliveryMethod === type ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'
                }`}
              >
                {label}
                {!isOffered && <span className="block text-[9px] font-bold mt-0.5">Vaqtincha mavjud emas</span>}
              </button>
            );
          })}
        </div>
        {deliveryMethod === null && (
          <div role="alert" className="bg-rose-50 border border-rose-100 p-3.5 rounded-2xl text-[10px] font-bold text-rose-600 flex items-start gap-2.5">
            <span className="text-sm">⚠️</span>
            <p>Do'kon hozircha yetkazib berishni ham, olib ketishni ham qabul qilmayapti. Savat saqlanadi, keyinroq urinib ko'ring.</p>
          </div>
        )}

      {/* Checkout Address Block (Only for delivery); nothing while the store takes no order at all */}
      {deliveryMethod === null ? null : deliveryMethod === 'delivery' ? (
      <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-3">
        <h3 className="text-[9px] font-black uppercase tracking-widest text-slate-400">Yetkazib berish manzili</h3>
        
        {showAddressForm ? (
          <div className="bg-slate-50 p-4 rounded-2xl border border-amber-500/20 flex flex-col gap-3 animate-fade-in">
            <p className="text-[10px] font-black uppercase tracking-wider text-slate-400">
              {editingAddressId !== null ? 'Manzilni tahrirlash' : 'Yangi manzil'}
            </p>
            <AddressLocationField
              key={editingAddressId ?? 'new'}
              value={addressDraft}
              onChange={patchAddressDraft}
              fallbackCenter={mapFallbackCenter}
            />
            <div className="grid grid-cols-2 gap-3">
              <input 
                type="text" placeholder="Xonadon"
                value={addressDraft.apartment} onChange={e => patchAddressDraft({ apartment: e.target.value })}
                className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors"
              />
              <input 
                type="text" placeholder="Qavat"
                value={addressDraft.floor} onChange={e => patchAddressDraft({ floor: e.target.value })}
                className="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs outline-none focus:border-amber-500 transition-colors"
              />
            </div>
            <div className="flex gap-2 pt-2">
              <button 
                onClick={closeAddressForm}
                className="flex-1 py-3 bg-slate-200 text-slate-600 font-bold rounded-xl text-xs active:scale-95 transition"
              >
                Bekor qilish
              </button>
              <button 
                onClick={handleSaveAddress}
                disabled={!canSaveAddress}
                className="flex-1 py-3 bg-amber-500 text-white font-bold rounded-xl text-xs active:scale-95 transition disabled:opacity-40 disabled:active:scale-100"
              >
                Saqlash
              </button>
            </div>
            {!canSaveAddress && (
              <p className="text-[10px] text-center font-bold text-slate-400 -mt-1">
                Saqlash uchun xaritada joylashuvni belgilang va manzilni yozing
              </p>
            )}
          </div>
        ) : (
          <div className="space-y-2">
            {addresses.map((addr) => {
              const hasMapPoint = getAddressPoint(addr) !== null;
              return (
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
                    {!hasMapPoint && (
                      <p className="text-[9px] text-rose-500 font-black mt-1">📍 Xaritada belgilanmagan</p>
                    )}
                  </div>
                  <button
                    type="button"
                    aria-label="Manzilni tahrirlash"
                    onClick={(e) => { e.stopPropagation(); openEditAddressForm(addr); }}
                    className="p-1.5 -m-1.5 text-slate-400 hover:text-amber-600 active:scale-90 transition"
                  >
                    <Pencil className="w-4 h-4" />
                  </button>
                  {selectedAddressId === addr.id && <CheckCircle2 className="w-4 h-4 text-amber-500 mt-1" />}
                </div>
              );
            })}
            <button 
              onClick={openNewAddressForm}
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
                {storeBranches.map((branch) => (
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
            const catalogVariant = findCatalogVariant(item.productId, item.variantId);
            const itemStock = getVariantStock(catalogVariant);
            const isAtStockLimit = itemStock !== null && item.quantity >= itemStock;
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
                      if (!canAddMore(catalogVariant, item.id)) return;
                      hapticFeedback('light');
                      updateQuantity(item.id, item.quantity + 1);
                    }} 
                    className={`w-6 h-6 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 transition ${isAtStockLimit ? 'opacity-40' : ''}`}
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
        <div role="group" aria-label="To'lov turi" className="grid grid-cols-3 gap-2">
          {availablePaymentMethods.map((method) => {
            const icon = method === 'click' ? '🔵' : method === 'payme' ? '🟢' : method === 'keshbek' ? '✨' : '💵';
            const label = method === 'naqd' ? 'Naqd' : method === 'keshbek' ? 'Keshbek' : method.toUpperCase();
            const isDisabled = method === 'keshbek' && !isKeshbekAffordable;
            return (
              <button
                key={method}
                type="button"
                disabled={isDisabled}
                aria-pressed={effectivePaymentMethod === method}
                onClick={() => {
                  hapticFeedback('light');
                  setChosenPaymentMethod(method);
                }}
                className={`py-3 px-1 rounded-2xl border text-[10px] font-black flex flex-col items-center justify-center gap-1 transition active:scale-95 duration-200 ${
                  isDisabled ? 'opacity-40 cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400' :
                  effectivePaymentMethod === method ? 'border-amber-500 bg-amber-50 text-amber-700 shadow-sm' : 'border-slate-100 bg-white text-slate-500'
                }`}
              >
                <span className="text-base leading-none">{icon}</span>
                <span className="uppercase tracking-wider">{label}</span>
                {method === 'keshbek' && balance !== undefined && (
                  <span className="text-[8px] text-slate-400 font-bold lowercase">({formatUzbekSum(balance)})</span>
                )}
              </button>
            );
          })}
        </div>
        {effectivePaymentMethod === null && (
          <p role="alert" className="text-[10px] text-rose-500 font-bold">
            {availablePaymentMethods.length === 0
              ? "Do'kon hozircha to'lov qabul qilmayapti. Keyinroq urinib ko'ring."
              : "Keshbek balansi buyurtma summasiga yetmaydi, do'kon esa boshqa to'lov usullarini qabul qilmayapti."}
          </p>
        )}
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
          disabled={isOrderDisabled}
          className={`w-full py-4 rounded-2xl font-black text-sm transition-all shadow-lg flex items-center justify-center gap-2 ${
            isOrderDisabled ? 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none' : 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-500/30 active:scale-95 hover:shadow-xl'
          }`}
        >
          {isOrdering ? (
            <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          ) : isStoreLocked ? (
            <>Do'kon vaqtincha yopiq</>
          ) : (
            <>Buyurtma berish - {formatUzbekSum(total)}</>
          )}
        </button>
        {deliveryMethod === null && (
          <p className="text-[10px] text-center text-rose-500 font-bold mt-2">Do'kon hozircha buyurtma qabul qilmayapti</p>
        )}
        {deliveryMethod !== null && effectivePaymentMethod === null && (
          <p className="text-[10px] text-center text-rose-500 font-bold mt-2">Buyurtma berish uchun mavjud to'lov usuli yo'q</p>
        )}
        {(deliveryMethod === 'delivery' && !selectedAddress) && (
          <p className="text-[10px] text-center text-rose-500 font-bold mt-2">Iltimos, yetkazib berish manzilini tanlang</p>
        )}
        {(deliveryMethod === 'delivery' && selectedAddress && !hasDeliveryLocation) && (
          <div className="mt-2 text-center">
            <p className="text-[10px] text-rose-500 font-bold">Tanlangan manzilning xaritadagi joylashuvi aniqlanmagan. Buyurtma berish uchun uni xaritada belgilang.</p>
            <button
              type="button"
              onClick={() => openEditAddressForm(selectedAddress)}
              className="mt-2 px-4 py-2 bg-amber-500/10 text-amber-600 rounded-xl text-[11px] font-black border border-amber-500/20 active:scale-95 transition"
            >
              📍 Xaritada belgilash
            </button>
          </div>
        )}
      </div>

      {/* Product Variant / Option Selector Sheet */}
      <VariantSelectorModal
        isOpen={!!selectedProductForVariant}
        onClose={() => setSelectedProductForVariant(null)}
        product={selectedProductForVariant}
        onAdd={(variant) => {
          if (!selectedProductForVariant) return;
          const cartItem = buildCartItem(selectedProductForVariant, variant);
          if (!canAddMore(variant, cartItem.id)) return;

          addItem(cartItem, getStoreInfo({ name: storeName, address: storeAddress }));
        }}
      />
      </div>
    </div>
  );
}
