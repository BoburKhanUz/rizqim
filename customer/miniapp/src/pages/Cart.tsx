import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { logApiError } from '../lib/apiError';
import { cartLineTotal, quantityOfVariant, useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import type { Address } from '../store/useAddressStore';
import { hapticFeedback, tg } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { showAlert, showConfirm } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { Button, Card, EmptyState, IconButton, PageHeader, ProductImage, Section, buttonClasses, cx, fieldClass } from '../components/ui';
import { OrderPlaced } from '../components/catalog/OrderPlaced';
import { QuantityStepper } from '../components/catalog/QuantityStepper';
import api from '../services/api';
import { AlertTriangle, Banknote, Bike, ChevronDown, CreditCard, MapPin, MessageSquare, Phone, Pencil, Plus, PlusCircle, ShoppingBag, Smartphone, Store, Ticket, Trash2, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import ProductSheet from '../components/ProductSheet';
import { DeliveryQuoteNote } from '../components/catalog/DeliveryQuoteNote';
import { SoldOutBadge } from '../components/catalog/SoldOutBadge';
import { useAuthStore } from '../store/authStore';
import { getAddBlockedMessage, getVariantStock } from '../lib/stock';
import { availabilityView, findCartIssues, isProductUnavailable } from '../lib/menuAvailability';
import { addQuantities, formatQuantity, isMeasuredUnit, subtractQuantities, unitLabel } from '../lib/quantity';
import { toOrderModifiers } from '../lib/modifiers';
import { createCheckoutAttempt } from '../lib/idempotency';
import { describeOrderFailure } from '../lib/orderErrors';
import { useAddToCart } from '../hooks/useAddToCart';
import { useDeliveryQuote } from '../hooks/useDeliveryQuote';
import { useMenuAvailability } from '../hooks/useMenuAvailability';
import { getAllowedPaymentMethods, getAvailableOrderTypes, pickOrderType, pickPaymentMethod } from '../lib/checkout';
import type { CartOrderType, CartPaymentMethod } from '../lib/checkout';
import { formatMoney } from '../lib/format';
import { getPaymentMethodLabel } from '../lib/paymentMethod';
import { useLanguageStore } from '../store/languageStore';
import { parseAmount, toAmount } from '../lib/money';
import { getProductBasePrice, getStoreInfo, parsePrice } from '../lib/catalog';
import { ORDERS_KEY } from '../hooks/useOrders';
import AddressLocationField from '../features/address/AddressLocationField';
import { isOnAnyBranch, toLatLng } from '../features/address/coordinates';
import type { AddressLocationValue, LatLng } from '../features/address/types';
import type { ApiErrorBody, ApiResponse, BranchSummary, Product, PromoValidation, StoreCatalog, StoreListItem } from '../types';

const NO_PRODUCTS: Product[] = [];

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

const ORDER_TYPE_TABS: ReadonlyArray<{ type: CartOrderType; label: string; icon: LucideIcon }> = [
  { type: 'delivery', label: 'Yetkazib berish', icon: Bike },
  { type: 'pickup', label: 'Olib ketish', icon: Store },
];

const PAYMENT_ICONS: Record<CartPaymentMethod, LucideIcon> = {
  naqd: Banknote,
  click: CreditCard,
  payme: Smartphone,
  keshbek: Wallet,
};

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
  const { t } = useLanguageStore();
  const queryClient = useQueryClient();
  const { user } = useAuthStore();
  const { items, storeName, storeAddress, updateQuantity, removeItem, clearCart, getSubtotal } = useCartStore();
  const { addresses, selectedAddressId, setSelectedAddressId, addAddress, updateAddress } = useAddressStore();
  const balance = user?.balance;

  const [deliveryNote, setDeliveryNote] = useState('');
  // The customer's own pick; null until they pick one (the method actually used is effectivePaymentMethod)
  const [chosenPaymentMethod, setChosenPaymentMethod] = useState<CartPaymentMethod | null>(null);
  const [promoCodeInput, setPromoCodeInput] = useState('');
  const [activePromo, setActivePromo] = useState<AppliedPromo | null>(null);
  const [promoError, setPromoError] = useState('');
  // The promo code and the courier note are one compact row each; they open on tap
  const [promoOpen, setPromoOpen] = useState(false);
  const [noteOpen, setNoteOpen] = useState(false);

  // The upsell catalog of the store it was fetched for, so a different store's products are never shown
  const [catalog, setCatalog] = useState<{ storeId: string; products: Product[] } | null>(null);
  const [catalogReload, setCatalogReload] = useState(0); // bumped when the menu may have changed under the cart

  // The customer's own pick (the way actually used is deliveryMethod, which only ever is one the store takes)
  const [chosenOrderType, setChosenOrderType] = useState<CartOrderType>('delivery');
  const [storeBranches, setStoreBranches] = useState<BranchSummary[]>([]);
  // Every branch of every store: a saved address sitting exactly on one of them is a leftover of the old cart
  // copying the restaurant's coordinates into hand-typed addresses (see isOnAnyBranch)
  const [branchPoints, setBranchPoints] = useState<LatLng[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  // The store's own delivery fee: only used for a store that has drawn no delivery zones (the quote prices the others)
  const [storeDeliveryFee, setStoreDeliveryFee] = useState(0);

  const [storeDetail, setStoreDetail] = useState<StoreListItem | null>(null);

  const [showAddressForm, setShowAddressForm] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState<number | null>(null);
  const [addressDraft, setAddressDraft] = useState<AddressDraft>(EMPTY_ADDRESS_DRAFT);
  const patchAddressDraft = useCallback((patch: Partial<AddressDraft>) => {
    setAddressDraft((prev) => ({ ...prev, ...patch }));
  }, []);

  const [isOrdering, setIsOrdering] = useState(false);
  const orderingRef = useRef(false);
  // One Idempotency-Key per checkout attempt, kept while the attempt is retried
  const attemptRef = useRef(createCheckoutAttempt());
  const [orderSuccess, setOrderSuccess] = useState(false);
  const [successOrderNumber, setSuccessOrderNumber] = useState('');

  // Scroll to top on mount
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, []);

  const cartStoreId = items[0]?.storeId;

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
            setStoreDeliveryFee(isNaN(rawFee) ? 0 : rawFee);
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
  }, [items, catalogReload]);

  // Cart items carry no stock info: look the variant up in the store catalog fetched above.
  const findCatalogVariant = (productId: number, variantId?: string) =>
    storeProducts.find(p => p.id === productId)?.variants?.find(v => v.uuid === variantId);

  // The menu of the cart's store; null until it has loaded (nothing is judged against a menu that is not there)
  const loadedProducts = catalog && catalog.storeId === cartStoreId ? catalog.products : null;
  const storeProducts = loadedProducts ?? NO_PRODUCTS;

  // What the store cannot offer now (stop-list, sold-out batches): kept current by events and polling. The branch that
  // cooks the order decides, so the view narrows to it once it is known (the delivery quote's, or the pickup branch)
  const { branches: availabilityBranches, refetch: refetchAvailability } = useMenuAvailability(cartStoreId);

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
  // The server decides whether the store can take orders (debt / finance status): `is_order_locked` on the
  // public store. A missing field (older backend) means not locked.
  const isStoreLocked = storeDetail?.is_order_locked === true;
  const selectedBranch = storeBranches.find(b => b.id === selectedBranchId) || storeBranches[0];
  // The map opens at the restaurant until the customer has dropped a pin (a view only, never saved as an address)
  const mapFallbackCenter = toLatLng(selectedBranch?.latitude, selectedBranch?.longitude);

  // Delivery orders are routed to a branch (and priced) by their coordinates, so we need real ones.
  const selectedAddress = addresses.find(a => a.id === selectedAddressId);
  const deliveryPoint = selectedAddress ? getAddressPoint(selectedAddress) : null;
  const hasDeliveryLocation = deliveryPoint !== null;

  // What delivery to the address costs, the minimum order and the time: asked from the server (GET /delivery/quote), which
  // prices the order the same way when it is placed. A store without zones keeps its own fee and the radius rule.
  const quote = useDeliveryQuote({
    storeId: cartStoreId,
    point: deliveryPoint,
    subtotal,
    enabled: deliveryMethod === 'delivery' && !isStoreLocked,
  });
  const quoted = quote.status === 'ready' ? quote.quote : null;
  const isQuoteBlocking = deliveryMethod === 'delivery' && hasDeliveryLocation && (quote.status === 'loading' || quote.status === 'refused' || quote.status === 'error');
  const deliveryFee = deliveryMethod !== 'delivery' ? 0 : quoted ? toAmount(quoted.delivery_price) : quote.status === 'unconfigured' ? storeDeliveryFee : 0;
  const total = Math.max(0, subtotal - discountAmount + deliveryFee);

  // The zone's minimum replaces the store's for a delivery; a store without zones, and pickup, keep the store's
  const storeMinOrder = storeDetail?.min_order !== undefined && storeDetail?.min_order !== null ? storeDetail.min_order : 0;
  const minOrderLimit = deliveryMethod === 'delivery' && quoted ? toAmount(quoted.min_order_amount) : storeMinOrder;
  const isMinOrderNotMet = deliveryMethod === 'delivery' && quoted ? quoted.meets_min_order === false : subtotal < storeMinOrder;

  // What the menu cannot offer now, as the branch that will cook the order sees it (every branch before it is known)
  const menuBranchId = deliveryMethod === 'delivery' ? quoted?.branch.id : deliveryMethod === 'pickup' ? selectedBranch?.id : undefined;
  const availability = useMemo(() => availabilityView(availabilityBranches, menuBranchId), [availabilityBranches, menuBranchId]);
  const issues = useMemo(() => findCartIssues(items, loadedProducts, availability), [items, loadedProducts, availability]);
  const issueOf = (itemId: string) => issues.find((issue) => issue.item.id === itemId);
  const hasIssues = issues.length > 0;

  // Dishes of the same restaurant offered below the list; the product sheet opens for the ones with choices
  const upsell = useAddToCart({ storeInfoOf: () => getStoreInfo({ name: storeName, address: storeAddress }), availability });
  const inCartProductIds = items.map(item => item.productId);
  const upsellRecommendations = storeProducts
    .filter(p => !inCartProductIds.includes(p.id) && p.variants && p.variants.length > 0 && !isProductUnavailable(p, availability))
    .slice(0, 3);

  // Cashback pays for the whole order or not at all; a choice that is no longer possible (store settings changed,
  // cart grew past the balance) quietly becomes the first method that is. Null when nothing can be paid with.
  const isKeshbekAffordable = balance !== undefined && balance >= total;
  const effectivePaymentMethod = pickPaymentMethod(availablePaymentMethods, chosenPaymentMethod, isKeshbekAffordable);

  const isOrderDisabled = isOrdering || isMinOrderNotMet || isStoreLocked || hasIssues || isQuoteBlocking
    || deliveryMethod === null || effectivePaymentMethod === null
    || (deliveryMethod === 'delivery' && !hasDeliveryLocation);

  // The menu may have changed under the cart (the server said so, or something sold out): ask for it again
  const refreshMenu = () => {
    setCatalogReload((count) => count + 1);
    void refetchAvailability();
  };

  const removeUnavailableLines = () => {
    hapticFeedback('medium');
    issues.forEach((issue) => removeItem(issue.item.id));
  };

  const handlePlaceOrder = async () => {
    // One order per press: a second tap (or a second MainButton event) while the first is being sent is dropped
    if (items.length === 0 || orderingRef.current) return;
    if (isStoreLocked) {
      hapticFeedback('error');
      void showAlert("Do'kon vaqtincha to'lov muammolari sababli buyurtma qabul qilmayapti. Savat saqlanadi, keyinroq urinib ko'ring.");
      return;
    }
    if (hasIssues) {
      hapticFeedback('error');
      void showAlert("Savatdagi ba'zi taomlar tugagan yoki menyudan olib tashlangan. Ularni olib tashlang, so'ng buyurtma bering.");
      return;
    }
    if (deliveryMethod === 'delivery' && isQuoteBlocking) {
      hapticFeedback('error');
      void showAlert(quote.status === 'refused' ? quote.message : quote.status === 'error'
        ? "Yetkazib berish narxini hisoblab bo'lmadi. Qayta urinib ko'ring."
        : "Yetkazib berish narxi hisoblanmoqda. Bir soniya kuting.");
      return;
    }
    if (isMinOrderNotMet) {
      void showAlert(`Minimal buyurtma summasi ${formatMoney(minOrderLimit)}. Iltimos, yana taom qo'shing.`);
      return;
    }
    // The server refuses an order its store does not take this way or with this payment
    if (deliveryMethod === null) {
      hapticFeedback('error');
      void showAlert("Do'kon hozircha yetkazib berishni ham, olib ketishni ham qabul qilmayapti. Keyinroq urinib ko'ring.");
      return;
    }
    if (effectivePaymentMethod === null) {
      hapticFeedback('error');
      void showAlert("Do'kon hozircha to'lov qabul qilmayapti yoki keshbek balansi yetarli emas. Keyinroq urinib ko'ring.");
      return;
    }
    if (deliveryMethod === 'delivery' && !selectedAddress) {
      void showAlert("Iltimos, yetkazib berish manzilini tanlang yoki yangisini qo'shing!");
      return;
    }

    // Never send made-up coordinates: they silently pick the wrong branch and delivery fee.
    if (deliveryMethod === 'delivery' && !deliveryPoint) {
      hapticFeedback('error');
      void showAlert("Tanlangan manzilning xaritadagi joylashuvi aniqlanmagan. Iltimos, manzilni tahrirlab, xaritada belgilang.");
      return;
    }

    if (deliveryMethod === 'pickup' && !selectedBranch) {
      void showAlert("Kechirasiz, restoranning faol filiali topilmadi.");
      return;
    }

    hapticFeedback('success');
    orderingRef.current = true;
    setIsOrdering(true);

    try {
      const payload = {
        store_id: parseInt(items[0].storeId),
        items: items.map(item => ({
          variant_id: item.variantId || item.id, // Must be UUID
          quantity: item.quantity, // a fraction for an item sold by weight (0.5 kg)
          // The chosen modifiers: [{id: uuid, quantity}]. Prices are never sent: the server prices every line itself
          ...(item.modifiers && item.modifiers.length > 0 ? { modifiers: toOrderModifiers(item.modifiers) } : {}),
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

      // The same key for a retry of the same order, so a repeated tap or a dropped connection never places two
      const idempotencyKey = attemptRef.current.keyFor(payload);
      const res = await api.post<ApiResponse<OrderResponseData>>('/orders', payload, { headers: { 'Idempotency-Key': idempotencyKey } });
      if (res.data.success) {
        attemptRef.current.finish();
        const orderData = res.data.data ?? {};
        setSuccessOrderNumber(getOrderDisplayNumber(orderData));
        // The customer's own channel brings no event for an order they placed: without this, a cached list of their
        // orders (refreshed slowly while the socket is up) could miss the new one when they go to look at it
        void queryClient.invalidateQueries({ queryKey: ORDERS_KEY });

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
        void showAlert(`${promoMessage}\n\nPromo-kod olib tashlandi. Iltimos, jami summani qayta tekshirib, buyurtmani qaytadan tasdiqlang.`);
      } else {
        // A sentence the customer can act on ("Tugadi", "bu manzilga yetkazib berilmaydi"), never a raw validation error
        const failure = describeOrderFailure(err);
        hapticFeedback('error');
        if (failure.refreshMenu) refreshMenu();
        if (failure.refreshQuote) quote.retry();
        void showAlert(failure.message);
      }
    } finally {
      orderingRef.current = false;
      setIsOrdering(false);
    }
  };

  const handleClearCart = async () => {
    hapticFeedback('light');
    const confirmed = await showConfirm("Savatdagi barcha taomlar olib tashlanadi.", {
      title: 'Savatni tozalash',
      confirmText: 'Tozalash',
      destructive: true,
    });
    if (confirmed) clearCart();
  };

  // The checkout action is Telegram's MainButton (or its stand-in in the bottom dock). The variant sheet takes it
  // over while it is open and gives it back when it closes (useMainButton keeps a stack); while an address is being
  // edited the form's own Save/Cancel are the actions, so it steps aside.
  useMainButton({
    text: isStoreLocked
      ? "Do'kon vaqtincha yopiq"
      : hasIssues
        ? "Tugaganlarni olib tashlang"
        : isQuoteBlocking && quote.status === 'refused'
          ? 'Bu manzilga yetkazib berilmaydi'
          : `Buyurtma berish · ${formatMoney(total)}`,
    onClick: handlePlaceOrder,
    enabled: !isOrderDisabled,
    progress: isOrdering,
    visible: items.length > 0 && !orderSuccess && !showAddressForm,
  });

  if (orderSuccess) {
    return <OrderPlaced orderNumber={successOrderNumber} />;
  }

  if (items.length === 0) {
    return (
      <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
        <PageHeader title="Savat" />
        <EmptyState
          icon={ShoppingBag}
          title="Savat bo'sh"
          text="Savatga hali hech narsa qo'shmadingiz. Katalogdan taom tanlang."
          action={
            <Link to="/" onClick={() => hapticFeedback('light')} className={buttonClasses()}>
              Katalogga o'tish
            </Link>
          }
        />
      </div>
    );
  }

  const radioIndicator = (selected: boolean) => (
    <span
      aria-hidden="true"
      className={cx(
        'flex size-6 shrink-0 items-center justify-center rounded-full border-2',
        selected ? 'border-brand' : 'border-fg-subtle',
      )}
    >
      {selected && <span className="size-3 rounded-full bg-brand" />}
    </span>
  );

  const CHEVRON = 'size-5 shrink-0 text-fg-muted transition-transform';
  const ROW = 'flex min-h-14 w-full cursor-pointer items-center gap-3 px-4 py-2 text-left';

  return (
    <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
      <PageHeader
        title="Savat"
        subtitle={`${storeName ? `${storeName} · ` : ''}${items.length} ta mahsulot`}
        action={<IconButton icon={Trash2} label="Savatni tozalash" variant="danger" onClick={handleClearCart} />}
      />

      {isStoreLocked && (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-danger-soft p-4 text-danger-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <div>
            <p className="text-body font-bold">Do'kon vaqtincha buyurtma qabul qilmayapti</p>
            <p className="text-footnote">Savat saqlanadi, keyinroq urinib ko'ring.</p>
          </div>
        </div>
      )}

      {hasIssues && (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-danger-soft p-4 text-danger-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <div className="flex min-w-0 flex-col items-start gap-2">
            <p className="text-body font-bold">Savatdagi ba'zi taomlar hozir buyurtma qilinmaydi</p>
            <p className="text-footnote">
              {issues.map((issue) => issue.item.name).join(', ')}. Ularni olib tashlang yoki boshqa taom tanlang: shundan keyin buyurtma berishingiz mumkin.
            </p>
            <Button variant="secondary" onClick={removeUnavailableLines}>
              Tugaganlarni olib tashlash
            </Button>
          </div>
        </div>
      )}

      {/* Delivery or pickup: a way the store switched off stays in sight, but can't be picked */}
      <div role="group" aria-label="Buyurtma turi" className="grid grid-cols-2 gap-1 rounded-card border border-line bg-surface p-1">
        {ORDER_TYPE_TABS.map(({ type, label, icon: Icon }) => {
          const isOffered = availableOrderTypes.includes(type);
          const isActive = deliveryMethod === type;
          return (
            <button
              key={type}
              type="button"
              disabled={!isOffered}
              aria-pressed={isActive}
              onClick={() => {
                selectionChanged();
                setChosenOrderType(type);
              }}
              className={cx(
                'flex min-h-11 flex-col items-center justify-center rounded-control px-2 py-1 transition-colors',
                !isOffered
                  ? 'cursor-not-allowed text-fg-subtle'
                  : isActive
                    ? 'cursor-pointer bg-brand text-on-brand'
                    : 'cursor-pointer text-fg-muted',
              )}
            >
              <span className="flex items-center gap-2 text-body font-bold">
                <Icon aria-hidden="true" className="size-[18px]" />
                {label}
              </span>
              {!isOffered && <span className="text-caption">Vaqtincha mavjud emas</span>}
            </button>
          );
        })}
      </div>
      {deliveryMethod === null && (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-danger-soft p-4 text-danger-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <p className="text-footnote">Do'kon hozircha yetkazib berishni ham, olib ketishni ham qabul qilmayapti. Savat saqlanadi, keyinroq urinib ko'ring.</p>
        </div>
      )}

      {/* Where: the delivery address, or the branch to pick up from; nothing while the store takes no order at all */}
      {deliveryMethod === null ? null : deliveryMethod === 'delivery' ? (
        <Section title="Yetkazib berish manzili">
          <Card className="flex flex-col gap-3">
            {showAddressForm ? (
              <div className="flex flex-col gap-3 rounded-card border border-line p-3 animate-fade-in">
                <p className="text-body font-bold text-fg">{editingAddressId !== null ? 'Manzilni tahrirlash' : 'Yangi manzil'}</p>
                <AddressLocationField
                  key={editingAddressId ?? 'new'}
                  value={addressDraft}
                  onChange={patchAddressDraft}
                  fallbackCenter={mapFallbackCenter}
                />
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="text"
                    placeholder="Xonadon"
                    aria-label="Xonadon"
                    value={addressDraft.apartment}
                    onChange={e => patchAddressDraft({ apartment: e.target.value })}
                    className={fieldClass}
                  />
                  <input
                    type="text"
                    placeholder="Qavat"
                    aria-label="Qavat"
                    value={addressDraft.floor}
                    onChange={e => patchAddressDraft({ floor: e.target.value })}
                    className={fieldClass}
                  />
                </div>
                <div className="flex gap-2">
                  <Button variant="secondary" onClick={closeAddressForm} className="flex-1">
                    Bekor qilish
                  </Button>
                  <Button onClick={handleSaveAddress} disabled={!canSaveAddress} className="flex-1">
                    Saqlash
                  </Button>
                </div>
                {!canSaveAddress && (
                  <p className="text-center text-footnote text-fg-muted">
                    Saqlash uchun xaritada joylashuvni belgilang va manzilni yozing
                  </p>
                )}
              </div>
            ) : (
              <>
                {addresses.length > 0 && (
                  <div role="radiogroup" aria-label="Manzillar" className="flex flex-col gap-2">
                    {addresses.map((addr) => {
                      const hasMapPoint = getAddressPoint(addr) !== null;
                      const isSelected = selectedAddressId === addr.id;
                      return (
                        <div
                          key={addr.id}
                          className={cx('flex items-center rounded-card border', isSelected ? 'border-brand bg-brand-soft' : 'border-line bg-surface')}
                        >
                          <button
                            type="button"
                            role="radio"
                            aria-checked={isSelected}
                            onClick={() => {
                              selectionChanged();
                              setSelectedAddressId(addr.id);
                            }}
                            className="flex min-h-14 min-w-0 flex-1 cursor-pointer items-center gap-3 px-3 py-2 text-left"
                          >
                            {radioIndicator(isSelected)}
                            <span className="min-w-0 flex-1">
                              <span className="flex items-center gap-2">
                                <span className="truncate text-body font-bold text-fg">{addr.title}</span>
                                {addr.is_default && (
                                  <span className="shrink-0 rounded-control border border-line bg-surface px-1.5 py-0.5 text-caption font-bold text-fg-muted">Asosiy</span>
                                )}
                              </span>
                              <span className="block truncate text-footnote text-fg-muted">{addr.address}</span>
                              {!hasMapPoint && (
                                <span className="mt-0.5 flex items-center gap-1 text-caption font-bold text-danger-soft-fg">
                                  <MapPin aria-hidden="true" className="size-3.5" /> Xaritada belgilanmagan
                                </span>
                              )}
                            </span>
                          </button>
                          <IconButton icon={Pencil} label="Manzilni tahrirlash" onClick={() => openEditAddressForm(addr)} className="mr-1" />
                        </div>
                      );
                    })}
                  </div>
                )}
                <Button variant="secondary" fullWidth icon={PlusCircle} onClick={openNewAddressForm}>
                  Yangi manzil qo'shish
                </Button>
                {!selectedAddress && (
                  <p role="status" className="text-footnote text-danger-soft-fg">Iltimos, yetkazib berish manzilini tanlang</p>
                )}
                {selectedAddress && hasDeliveryLocation && (
                  <DeliveryQuoteNote
                    quote={quote}
                    onChoosePickup={
                      availableOrderTypes.includes('pickup')
                        ? () => {
                            selectionChanged();
                            setChosenOrderType('pickup');
                          }
                        : undefined
                    }
                  />
                )}
                {selectedAddress && !hasDeliveryLocation && (
                  <div role="status" className="flex flex-col items-start gap-2 rounded-card bg-warning-soft p-3 text-warning-soft-fg">
                    <p className="text-footnote">
                      Tanlangan manzilning xaritadagi joylashuvi aniqlanmagan. Buyurtma berish uchun uni xaritada belgilang.
                    </p>
                    <Button variant="secondary" icon={MapPin} onClick={() => openEditAddressForm(selectedAddress)}>
                      Xaritada belgilash
                    </Button>
                  </div>
                )}
              </>
            )}
          </Card>
        </Section>
      ) : (
        <Section title="Olib ketish joyi">
          <Card className="flex flex-col gap-3">
            {storeBranches.length > 1 && (
              <label className="flex flex-col gap-1.5">
                <span className="text-footnote text-fg-muted">Filialni tanlang</span>
                <select
                  value={selectedBranchId || ""}
                  onChange={(e) => {
                    const id = parseInt(e.target.value);
                    setSelectedBranchId(id);
                    selectionChanged();
                  }}
                  className={fieldClass}
                >
                  {storeBranches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </select>
              </label>
            )}

            {selectedBranch ? (
              <div className="flex items-start gap-3 rounded-card bg-surface-2 p-3">
                <Store aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-brand" />
                <div className="min-w-0">
                  <p className="text-body font-bold text-fg">{selectedBranch.name || storeName || "Siz tanlagan oshxona"}</p>
                  <p className="text-footnote text-fg-muted">{selectedBranch.address || storeAddress || "Filial manzili ko'rsatilmagan"}</p>
                  {selectedBranch.phone && (
                    <p className="mt-1 flex items-center gap-1 text-footnote text-fg-muted">
                      <Phone aria-hidden="true" className="size-3.5" /> {selectedBranch.phone}
                    </p>
                  )}
                </div>
              </div>
            ) : (
              <p className="text-footnote text-danger-soft-fg">Faol filiallar topilmadi</p>
            )}
          </Card>
        </Section>
      )}

      {/* The items */}
      <Card padded={false}>
        <ul className="divide-y divide-line">
          {items.map((item) => {
            const catalogVariant = findCatalogVariant(item.productId, item.variantId);
            const issue = issueOf(item.id);
            const measured = isMeasuredUnit(item.unit);
            const step = item.quantityStep ?? 0.1;
            const minimum = item.minQuantity ?? step;
            // The stock is the variant's: lines of it with other modifiers count too
            const variantInCart = quantityOfVariant(items, item.variantId);
            const itemStock = getVariantStock(catalogVariant);
            const isAtStockLimit = itemStock !== null && addQuantities(variantInCart, measured ? step : 1) > itemStock;
            return (
              <li key={item.id} className="flex gap-3 p-3">
                <ProductImage src={item.image} className={cx('size-14 rounded-control', issue && 'opacity-50 grayscale')} />
                <div className="min-w-0 flex-1">
                  <p className="line-clamp-2 text-body font-bold text-fg">{item.name}</p>
                  {item.modifiers && item.modifiers.length > 0 && (
                    <ul aria-label="Tanlangan qo'shimchalar" className="mt-0.5 text-footnote text-fg-muted">
                      {item.modifiers.map((modifier) => (
                        <li key={modifier.uuid}>
                          {modifier.name}
                          {modifier.quantity > 1 ? ` × ${modifier.quantity}` : ''}
                          {modifier.priceDelta !== 0 ? ` · +${formatMoney(modifier.priceDelta * modifier.quantity)}` : ''}
                        </li>
                      ))}
                    </ul>
                  )}
                  {issue && (
                    <p role="status" className="mt-1 flex flex-wrap items-center gap-1.5 text-footnote font-bold text-danger-soft-fg">
                      {issue.problem === 'sold_out' && <SoldOutBadge />}
                      {issue.problem === 'sold_out' ? 'Bu taom hozir mavjud emas' : issue.message}
                    </p>
                  )}
                  <div className="mt-1 flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                    <div>
                      <p className={cx('text-body font-bold', issue ? 'text-fg-muted' : 'text-fg')}>{formatMoney(cartLineTotal(item))}</p>
                      {measured ? (
                        <p className="text-caption text-fg-muted">
                          {formatMoney(item.price)} / {unitLabel(item.unit)} · {formatQuantity(item.quantity, item.unit)}
                        </p>
                      ) : (
                        item.quantity > 1 && <p className="text-caption text-fg-muted">{formatMoney(item.price)} × {item.quantity}</p>
                      )}
                    </div>
                    <QuantityStepper
                      quantity={item.quantity}
                      valueLabel={measured ? formatQuantity(item.quantity, item.unit) : undefined}
                      removeAtOne
                      removeAt={measured ? minimum : 1}
                      incrementDimmed={isAtStockLimit || !!issue}
                      decrementLabel={item.quantity <= (measured ? minimum : 1) ? `${item.name}: savatdan olib tashlash` : `${item.name}: kamaytirish`}
                      incrementLabel={`${item.name}: ko'paytirish`}
                      onDecrement={() => {
                        hapticFeedback('medium');
                        const next = subtractQuantities(item.quantity, measured ? step : 1);
                        if (next < (measured ? minimum : 1)) {
                          removeItem(item.id);
                        } else {
                          updateQuantity(item.id, next);
                        }
                      }}
                      onIncrement={() => {
                        if (issue) {
                          hapticFeedback('error');
                          void showAlert(issue.message);
                          return;
                        }
                        const blocked = getAddBlockedMessage(catalogVariant, variantInCart, measured ? step : 1);
                        if (blocked) {
                          hapticFeedback('error');
                          void showAlert(blocked);
                          return;
                        }
                        hapticFeedback('light');
                        updateQuantity(item.id, addQuantities(item.quantity, measured ? step : 1));
                      }}
                      className="ml-auto"
                    />
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      </Card>

      {/* Upsell: a few more dishes of the same restaurant */}
      {upsellRecommendations.length > 0 && (
        <Section title="Buyurtmaga qo'shishni unutmadingizmi?">
          <div className="scrollbar-hide -mx-4 flex gap-3 overflow-x-auto px-4">
            {upsellRecommendations.map((product) => (
              <div key={product.id} className="flex w-36 shrink-0 flex-col gap-2 rounded-card border border-line bg-surface p-2">
                <ProductImage src={product.image_url} className="aspect-square w-full rounded-control" />
                <div className="min-w-0 flex-1">
                  <h3 className="line-clamp-2 text-footnote font-bold text-fg">{product.name}</h3>
                  <p className="mt-0.5 text-footnote text-fg-muted">{formatMoney(getProductBasePrice(product))}{product.variants?.some((variant) => isMeasuredUnit(variant.unit)) ? ` / ${unitLabel('kg')}` : ''}</p>
                </div>
                <Button variant="secondary" icon={Plus} fullWidth onClick={() => upsell.start(product)}>
                  Qo'shish
                </Button>
              </div>
            ))}
          </div>
        </Section>
      )}

      {/* Promo code and the note for the courier: one compact row each */}
      <Card padded={false} className="divide-y divide-line">
        <div>
          {activePromo ? (
            <div className="flex min-h-14 items-center gap-3 px-4 py-2">
              <Ticket aria-hidden="true" className="size-5 shrink-0 text-brand" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-body font-bold text-fg">{activePromo.code} faol</p>
                {activePromo.description && <p className="truncate text-footnote text-fg-muted">{activePromo.description}</p>}
              </div>
              <Button variant="ghost" onClick={removePromoCode}>
                Bekor qilish
              </Button>
            </div>
          ) : (
            <>
              <button type="button" aria-expanded={promoOpen} onClick={() => setPromoOpen((open) => !open)} className={ROW}>
                <Ticket aria-hidden="true" className="size-5 shrink-0 text-brand" />
                <span className="flex-1 text-body font-medium text-fg">Promo-kod</span>
                <ChevronDown aria-hidden="true" className={cx(CHEVRON, promoOpen && 'rotate-180')} />
              </button>
              {promoOpen && (
                <div className="flex gap-2 px-4 pb-3">
                  <input
                    type="text"
                    value={promoCodeInput}
                    onChange={(e) => setPromoCodeInput(e.target.value.toUpperCase())}
                    placeholder="Promo-kodni kiriting"
                    aria-label="Promo-kod"
                    className={`${fieldClass} flex-1`}
                  />
                  <Button onClick={() => applyPromoCode(promoCodeInput)}>Qo'llash</Button>
                </div>
              )}
            </>
          )}
          {promoError && <p role="alert" className="px-4 pb-3 text-footnote text-danger-soft-fg">{promoError}</p>}
        </div>
        <div>
          <button type="button" aria-expanded={noteOpen} onClick={() => setNoteOpen((open) => !open)} className={ROW}>
            <MessageSquare aria-hidden="true" className="size-5 shrink-0 text-brand" />
            <span className="min-w-0 flex-1">
              <span className="block text-body font-medium text-fg">{deliveryMethod === 'pickup' ? 'Izoh' : 'Kuryer uchun izoh'}</span>
              {deliveryNote && !noteOpen && <span className="block truncate text-footnote text-fg-muted">{deliveryNote}</span>}
            </span>
            <ChevronDown aria-hidden="true" className={cx(CHEVRON, noteOpen && 'rotate-180')} />
          </button>
          {noteOpen && (
            <div className="px-4 pb-3">
              <textarea
                rows={2}
                placeholder="Uy raqami, podyezd, dom kodi yoki xohishlaringiz"
                aria-label="Izoh"
                value={deliveryNote}
                onChange={(e) => setDeliveryNote(e.target.value)}
                className={cx(fieldClass, 'resize-none py-2.5')}
              />
            </div>
          )}
        </div>
      </Card>

      {/* Payment method: a vertical list of radios */}
      <Section title="To'lov usuli">
        <Card padded={false}>
          <div role="radiogroup" aria-label="To'lov usuli" className="divide-y divide-line">
            {availablePaymentMethods.map((method) => {
              const Icon = PAYMENT_ICONS[method];
              const label = getPaymentMethodLabel(method, t);
              const isDisabled = method === 'keshbek' && !isKeshbekAffordable;
              const isSelected = effectivePaymentMethod === method;
              return (
                <button
                  key={method}
                  type="button"
                  role="radio"
                  aria-checked={isSelected}
                  disabled={isDisabled}
                  onClick={() => {
                    selectionChanged();
                    setChosenPaymentMethod(method);
                  }}
                  className={cx(ROW, 'disabled:cursor-not-allowed disabled:opacity-60')}
                >
                  <Icon aria-hidden="true" className={cx('size-5 shrink-0', isSelected ? 'text-brand' : 'text-fg-muted')} />
                  <span className="min-w-0 flex-1">
                    <span className="block text-body font-medium text-fg">{label}</span>
                    {method === 'keshbek' && balance !== undefined && (
                      <span className="block text-footnote text-fg-muted">
                        Balans: {formatMoney(balance)}{isDisabled ? " · yetarli emas" : ''}
                      </span>
                    )}
                  </span>
                  {radioIndicator(isSelected)}
                </button>
              );
            })}
          </div>
        </Card>
        {effectivePaymentMethod === null && (
          <p role="alert" className="px-1 text-footnote text-danger-soft-fg">
            {availablePaymentMethods.length === 0
              ? "Do'kon hozircha to'lov qabul qilmayapti. Keyinroq urinib ko'ring."
              : "Keshbek balansi buyurtma summasiga yetmaydi, do'kon esa boshqa to'lov usullarini qabul qilmayapti."}
          </p>
        )}
      </Section>

      {/* Total (the checkout button is the MainButton) */}
      <Card className="flex flex-col gap-3">
        {isMinOrderNotMet && (
          <div role="alert" className="flex items-start gap-3 rounded-control bg-warning-soft p-3 text-warning-soft-fg">
            <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
            <div>
              <p className="text-footnote font-bold">Minimal buyurtma miqdori yetarli emas</p>
              <p className="text-footnote">
                Bu oshxonadan buyurtma berish uchun kamida {formatMoney(minOrderLimit)} lik mahsulot qo'shing (hozir: {formatMoney(subtotal)}).
              </p>
            </div>
          </div>
        )}
        <dl className="flex flex-col gap-2 text-body">
          <div className="flex justify-between gap-3">
            <dt className="text-fg-muted">Taomlar qiymati</dt>
            <dd className="font-bold text-fg">{formatMoney(subtotal)}</dd>
          </div>
          {activePromo && (
            <div className="flex justify-between gap-3 text-success-soft-fg">
              <dt>Promo-kod chegirmasi ({activePromo.amount}{activePromo.type === 'percentage' ? '%' : " so'm"})</dt>
              <dd className="shrink-0 font-bold">- {formatMoney(discountAmount)}</dd>
            </div>
          )}
          {deliveryMethod === 'delivery' && (
            <div className="flex justify-between gap-3">
              <dt className="text-fg-muted">Kuryer xizmati</dt>
              <dd className="font-bold text-fg">
                {quote.status === 'ready' || quote.status === 'unconfigured'
                  ? deliveryFee > 0 ? formatMoney(deliveryFee) : 'Bepul'
                  : quote.status === 'loading' ? 'Hisoblanmoqda…' : '—'}
              </dd>
            </div>
          )}
        </dl>
        <div className="flex items-baseline justify-between gap-3 border-t border-line pt-3">
          <span className="text-headline font-bold text-fg">Jami</span>
          <span className="text-title font-extrabold text-fg">{formatMoney(total)}</span>
        </div>
      </Card>

      {/* The product sheet of an upsell dish: variant, modifiers, weight */}
      <ProductSheet
        isOpen={!!upsell.sheetProduct}
        onClose={upsell.closeSheet}
        product={upsell.sheetProduct}
        availability={availability}
        onAdd={(choice) => {
          if (upsell.sheetProduct) upsell.addVariant(upsell.sheetProduct, choice.variant, choice);
        }}
      />
    </div>
  );
}
