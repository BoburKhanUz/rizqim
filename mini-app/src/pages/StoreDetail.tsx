import React, { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useCartStore, type PendingCartItem } from '../store/cartStore';
import { hapticFeedback } from '../lib/telegram';
import { ErrorFallback } from '../components/Feedback';
import api from '../services/api';
import { ArrowLeft, Heart, Star, Minus, Plus } from 'lucide-react';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { getAddBlockedMessage, getVariantStock, isProductSoldOut, type StockVariant } from '../lib/stock';
import { formatUzbekSum } from '../lib/format';
import { buildCartItem, getProductBasePrice, getStoreInfo } from '../lib/catalog';
import { getErrorMessage } from '../lib/apiError';
import type { ApiResponse, Category, Product, ProductVariant, StoreCatalog, StoreCatalogStore } from '../types';

export default function StoreDetail() {
  const { id: uuid } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { items: cartItems, addItem, forceClearAndAdd, removeItem } = useCartStore();
  const [pendingCartItem, setPendingCartItem] = useState<PendingCartItem | null>(null);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<Product | null>(null);

  const [store, setStore] = useState<StoreCatalogStore | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);

  const [selectedParentCategoryId, setSelectedParentCategoryId] = useState<number | null>(null);
  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState<number | null>(null);
  const [activeSegment, setActiveSegment] = useState<'menu' | 'reviews'>('menu');
  const [isFav, setIsFav] = useState(false); // Mock favorite state

  useEffect(() => {
    if (!uuid) return;
    let cancelled = false;
    const fetchStoreCatalog = async () => {
      try {
        const res = await api.get<ApiResponse<StoreCatalog>>(`/catalog?store_id=${uuid}`);
        if (res.data.success && !cancelled) {
          setStore(res.data.data.store);
          setCategories(res.data.data.categories);
          setProducts(res.data.data.products);
        }
      } catch (err) {
        if (!cancelled) setError(err);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchStoreCatalog();
    return () => {
      cancelled = true;
    };
  }, [uuid]);

  // A promo banner links here with ?product_id=...: that product's selector opens once the menu has loaded,
  // until the customer closes it.
  const targetProductId = searchParams.get('product_id');
  const [dismissedTargetId, setDismissedTargetId] = useState<string | null>(null);
  const targetProduct = useMemo(() => {
    if (!targetProductId || targetProductId === dismissedTargetId) return null;
    return products.find(p => p.id.toString() === targetProductId) ?? null;
  }, [targetProductId, dismissedTargetId, products]);
  const variantModalProduct = selectedProductForVariant ?? targetProduct;

  const parentCategories = useMemo(() => {
    return categories.filter(c => !c.parent_id);
  }, [categories]);

  const subCategories = useMemo(() => {
    if (!selectedParentCategoryId) return [];
    return categories.filter(c => c.parent_id === selectedParentCategoryId);
  }, [categories, selectedParentCategoryId]);

  const filteredProducts = useMemo(() => {
    let filtered = products;
    if (selectedSubCategoryId) {
      filtered = filtered.filter(p => p.category_id === selectedSubCategoryId);
    } else if (selectedParentCategoryId) {
      const subIds = categories.filter(c => c.parent_id === selectedParentCategoryId).map(c => c.id);
      filtered = filtered.filter(p => p.category_id === selectedParentCategoryId || subIds.includes(p.category_id));
    }
    return filtered;
  }, [products, categories, selectedParentCategoryId, selectedSubCategoryId]);

  const groupedProducts = useMemo(() => {
    const groups: Record<string, Product[]> = {};
    filteredProducts.forEach(product => {
      const category = categories.find(c => c.id === product.category_id);
      const catName = category ? category.name : 'Boshqalar';
      if (!groups[catName]) {
        groups[catName] = [];
      }
      groups[catName].push(product);
    });
    return groups;
  }, [filteredProducts, categories]);

  const toggleFavoriteStore = () => {
    hapticFeedback('light');
    setIsFav(!isFav);
  };

  // Mirrors VariantSelectorModal: a variant with stock <= 0 can't be added,
  // and the cart can never hold more of a variant than is in stock.
  const canAddVariant = (variant: StockVariant | undefined, cartItemId: string): boolean => {
    const inCart = cartItems.find(item => item.id === cartItemId)?.quantity ?? 0;
    const blocked = getAddBlockedMessage(variant, inCart);
    if (blocked) {
      hapticFeedback('error');
      alert(blocked);
      return false;
    }
    return true;
  };

  // Puts one unit of the variant into the cart (unless the stock rule blocks it); another store's items in the cart
  // ask the customer first
  const addToCart = (product: Product, variant: ProductVariant | undefined) => {
    const cartItem = buildCartItem(product, variant);
    if (!canAddVariant(variant, cartItem.id)) return;

    const storeInfo = getStoreInfo(store);

    const isSuccess = addItem(cartItem, storeInfo);
    if (!isSuccess) {
      setPendingCartItem({ item: cartItem, storeInfo });
    }
  };

  const handleAddToCart = (e: React.MouseEvent, product: Product) => {
    e.stopPropagation();
    hapticFeedback('medium');

    if (product.variants && product.variants.length > 1) {
      setSelectedProductForVariant(product);
      return;
    }

    addToCart(product, product.variants?.[0]);
  };

  const handleRemoveFromCart = (e: React.MouseEvent, productId: string) => {
    e.stopPropagation();
    hapticFeedback('light');
    removeItem(productId);
  };

  if (error) return <ErrorFallback error={getErrorMessage(error) || "Do'konni yuklashda xatolik"} />;
  if (loading || !store) {
    return (
      <div className="pb-28 bg-white min-h-screen">
        {/* Header Banner Skeleton */}
        <div className="h-52 bg-slate-100 animate-pulse relative">
          <div className="absolute top-4 left-4 right-4 flex items-center justify-between">
            <div className="w-10 h-10 rounded-2xl bg-slate-200"></div>
            <div className="w-10 h-10 rounded-2xl bg-slate-200"></div>
          </div>
          <div className="absolute bottom-4 left-4 right-4 flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-slate-200"></div>
            <div className="space-y-1.5">
              <div className="h-4 w-32 bg-slate-200 rounded"></div>
              <div className="h-3 w-24 bg-slate-200 rounded"></div>
            </div>
          </div>
        </div>

        {/* Details Skeleton */}
        <div className="bg-white p-4 border-b border-slate-100 space-y-4">
          <div className="h-3 w-3/4 bg-slate-100 rounded animate-pulse"></div>
          <div className="grid grid-cols-2 gap-2.5">
            <div className="h-10 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"></div>
            <div className="h-10 bg-slate-50 rounded-2xl border border-slate-100 animate-pulse"></div>
          </div>
        </div>

        {/* Segments Skeleton */}
        <div className="flex border-b border-slate-100 bg-white px-4 py-3 gap-4">
          <div className="flex-1 h-4 bg-slate-100 rounded animate-pulse"></div>
          <div className="flex-1 h-4 bg-slate-100 rounded animate-pulse"></div>
        </div>

        {/* Products List Skeleton */}
        <div className="p-4 space-y-4">
          <div className="flex gap-2.5 overflow-x-auto scrollbar-hide py-1">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-8 w-20 bg-slate-100 rounded-2xl animate-pulse shrink-0"></div>
            ))}
          </div>

          <div className="space-y-3 pt-2">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="bg-white rounded-3xl p-3 border border-slate-100 flex items-center justify-between gap-3">
                <div className="flex-1 space-y-2">
                  <div className="h-3 w-1/3 bg-slate-100 rounded animate-pulse"></div>
                  <div className="h-2 w-3/4 bg-slate-100 rounded animate-pulse"></div>
                  <div className="h-3 w-1/4 bg-slate-100 rounded animate-pulse"></div>
                </div>
                <div className="w-16 h-16 rounded-2xl bg-slate-100 animate-pulse shrink-0"></div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  const initials = store.name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();

  return (
    <div className="pb-28 animate-fade-in bg-white min-h-screen">
      {/* Banner Cover with favorite toggle */}
      <div className="h-52 relative w-full bg-slate-900">
        {store.banner_url ? (
          <img src={store.banner_url} alt="" className="w-full h-full object-cover opacity-90" />
        ) : (
          <div className="w-full h-full bg-amber-500/20" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"></div>
        
        {/* Native Floating Controls */}
        <div className="absolute top-4 left-4 right-4 flex items-center justify-between z-10">
          <button 
            onClick={() => navigate('/')} 
            className="w-10 h-10 rounded-2xl bg-white/90 backdrop-blur-md text-slate-800 flex items-center justify-center shadow-lg hover:scale-105 active:scale-95 transition"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <button 
            onClick={toggleFavoriteStore}
            className="w-10 h-10 rounded-2xl bg-white/90 backdrop-blur-md text-slate-800 flex items-center justify-center shadow-lg hover:scale-105 active:scale-95 transition"
          >
            <Heart className={`w-5 h-5 ${isFav ? 'fill-rose-500 text-rose-500' : ''}`} />
          </button>
        </div>

        <div className="absolute bottom-4 left-4 right-4 text-white">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/95 backdrop-blur-md flex items-center justify-center text-xl font-black text-amber-500 shadow-md border border-white/20 shrink-0 overflow-hidden">
              {store.logo_url ? (
                <img src={store.logo_url} className="w-full h-full object-cover" alt="" />
              ) : (
                <span>{initials}</span>
              )}
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-lg font-black tracking-tight leading-tight">{store.name}</h1>
                <span className={`text-[9px] font-black px-2 py-0.5 rounded ${
                  store.is_open ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'
                }`}>
                  {store.is_open ? "🟢 Ochiq" : "🔴 Yopiq"}
                </span>
              </div>
              <p className="text-[10px] text-white/90 font-bold flex items-center gap-1 mt-0.5">
                <Star className="w-3 h-3 fill-amber-500 text-amber-500" /> {store.rating || "4.5"} ({store.review_count || 0} sharhlar) • {store.delivery_time || "20-30 min"}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Description Info and constraints card */}
      <div className="bg-white p-4 rounded-b-3xl border-b border-slate-100 space-y-3.5 shadow-sm">
        <p className="text-xs text-slate-500 font-semibold leading-relaxed">{store.description || "Mazali taomlar"}</p>
        <div className="grid grid-cols-2 gap-2.5 text-[10px] text-center font-bold">
          <div className="bg-slate-50 p-3 rounded-2xl border border-slate-100 text-slate-600">
            Minimal buyurtma: <span className="block font-black text-slate-800 mt-0.5">{formatUzbekSum(store.min_order !== undefined && store.min_order !== null ? store.min_order : 30000)}</span>
          </div>
          <div className="bg-slate-50 p-3 rounded-2xl border border-slate-100 text-slate-600">
            Kuryer xizmati: <span className="block font-black text-slate-800 mt-0.5">{formatUzbekSum(store.delivery_fee !== undefined && store.delivery_fee !== null ? store.delivery_fee : 10000)}</span>
          </div>
        </div>
      </div>

      {/* Segment Navigation */}
      <div className="flex border-b border-slate-100 bg-white sticky top-0 z-10 px-4 pt-2">
        <button 
          onClick={() => setActiveSegment('menu')}
          className={`flex-1 py-3 text-xs font-black border-b-2 transition-all ${
            activeSegment === 'menu' ? 'border-amber-500 text-amber-600' : 'border-transparent text-slate-400'
          }`}
        >
          Menyu
        </button>
        <button 
          onClick={() => setActiveSegment('reviews')}
          className={`flex-1 py-3 text-xs font-black border-b-2 transition-all ${
            activeSegment === 'reviews' ? 'border-amber-500 text-amber-600' : 'border-transparent text-slate-400'
          }`}
        >
          Fikrlar
        </button>
      </div>

      {activeSegment === 'menu' ? (
        <div className="p-4 space-y-6">
          {/* Categories Selector */}
          {parentCategories.length > 0 && (
            <div className="space-y-3 pb-2 border-b border-slate-50">
              <div className="flex gap-2.5 overflow-x-auto scrollbar-hide py-1">
                {parentCategories.map((cat) => {
                  const isSelected = selectedParentCategoryId === cat.id;
                  return (
                    <button
                      key={cat.id}
                      onClick={() => {
                        hapticFeedback('light');
                        setSelectedParentCategoryId(isSelected ? null : cat.id);
                        setSelectedSubCategoryId(null);
                      }}
                      className={`px-4 py-2 rounded-2xl text-xs font-black whitespace-nowrap transition-all ${
                        isSelected 
                          ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' 
                          : 'bg-slate-50 text-slate-600 border border-slate-100 hover:bg-slate-100'
                      }`}
                    >
                      {cat.name}
                    </button>
                  );
                })}
              </div>

              {/* Subcategories (Second Row) */}
              {selectedParentCategoryId && subCategories.length > 0 && (
                <div className="flex gap-2 overflow-x-auto scrollbar-hide py-1 pl-1 border-l-2 border-amber-500/30">
                  {subCategories.map((subCat) => {
                    const isSelected = selectedSubCategoryId === subCat.id;
                    return (
                      <button
                        key={subCat.id}
                        onClick={() => {
                          hapticFeedback('light');
                          setSelectedSubCategoryId(isSelected ? null : subCat.id);
                        }}
                        className={`px-3 py-1.5 rounded-xl text-[10px] font-bold whitespace-nowrap transition-all ${
                          isSelected 
                            ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20' 
                            : 'bg-white text-slate-500 border border-slate-100 hover:bg-slate-50'
                        }`}
                      >
                        {subCat.name}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {Object.entries(groupedProducts).map(([catName, productsGroup]) => {
            return (
              <div key={catName} className="space-y-3">
                <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-wider pl-2.5 border-l-4 border-amber-500">
                  {catName}
                </h3>
                <div className="space-y-2.5">
                  {productsGroup.map((product) => {
                    const cartItemsArray = cartItems.filter(item => item.productId === product.id);
                    const cartQty = cartItemsArray.reduce((sum, item) => sum + item.quantity, 0);
                    const isSoldOut = isProductSoldOut(product);
                    // Single-variant products: the "+" stops at the available stock
                    const singleVariantStock = product.variants?.length === 1 ? getVariantStock(product.variants[0]) : null;
                    const isAtStockLimit = singleVariantStock !== null && cartQty >= singleVariantStock;

                    return (
                      <div 
                        key={product.id} 
                        className={`bg-white rounded-3xl p-3 border border-slate-100 flex items-center justify-between gap-3 shadow-[0_8px_30px_rgb(0,0,0,0.02)] transition-all duration-200 ${
                          !store.is_open ? 'opacity-60 cursor-not-allowed' : isSoldOut ? 'opacity-60' : 'hover:shadow-sm active:scale-[0.98]'
                        }`}
                      >
                        <div className="flex-1 min-w-0">
                          <h4 className="text-xs font-black text-slate-800 leading-tight">{product.name}</h4>
                          <p className="text-[10px] text-slate-400 font-medium line-clamp-2 mt-1 leading-relaxed">{product.description}</p>
                          <span className="text-xs font-black text-slate-800 block mt-2">{formatUzbekSum(getProductBasePrice(product))}</span>
                        </div>
                        <div className="flex flex-col items-center gap-2 shrink-0">
                          <div className="w-16 h-16 rounded-2xl overflow-hidden relative border border-slate-100 bg-slate-50 flex items-center justify-center">
                            {product.image_url ? (
                              <img src={product.image_url} alt="" className="w-full h-full object-cover" />
                            ) : (
                              <span className="text-2xl">🍔</span>
                            )}
                          </div>
                          {store.is_open ? (
                            cartQty > 0 ? (
                              <div className="flex items-center bg-amber-500 text-white rounded-full p-0.5 scale-90" onClick={e => e.stopPropagation()}>
                                <>
                                  <button onClick={(e) => handleRemoveFromCart(e, cartItemsArray[0].id)} className="w-6 h-6 flex items-center justify-center rounded-full text-white active:scale-90 transition">
                                    <Minus className="w-3 h-3" />
                                  </button>
                                  <span className="px-1.5 text-xs font-black">{cartQty}</span>
                                  <button onClick={(e) => handleAddToCart(e, product)} className={`w-6 h-6 flex items-center justify-center rounded-full text-white active:scale-90 transition ${isSoldOut || isAtStockLimit ? 'opacity-40' : ''}`}>
                                    <Plus className="w-3 h-3" />
                                  </button>
                                </>
                              </div>
                            ) : isSoldOut ? (
                              <button
                                disabled
                                className="bg-slate-100 text-red-500 px-3 py-1.5 rounded-full font-extrabold text-[9px] border border-slate-200 whitespace-nowrap cursor-not-allowed"
                              >
                                Tugagan
                              </button>
                            ) : (
                              <button 
                                onClick={(e) => handleAddToCart(e, product)} 
                                className="bg-amber-50 hover:bg-amber-100 text-amber-600 px-3 py-1.5 rounded-full font-black text-[9px] border border-amber-100 transition whitespace-nowrap active:scale-90"
                              >
                                Qo'shish +
                              </button>
                            )
                          ) : (
                            <span className="text-[8px] bg-slate-100 text-slate-400 font-bold px-2 py-1 rounded">Yopiq</span>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      ) : (
        <div className="p-4 space-y-3">
          {store?.ratings && store.ratings.length > 0 ? (
            store.ratings.map((rate) => (
              <div key={rate.id} className="bg-white p-4 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.01)] space-y-2">
                <div className="flex justify-between items-center">
                  <div>
                    <p className="text-xs font-black text-slate-800">{rate.user?.name || "Mijoz"}</p>
                    <p className="text-[9px] text-slate-400 font-bold mt-0.5">
                      {new Date(rate.created_at).toLocaleDateString('uz-UZ', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                      })}
                    </p>
                  </div>
                  <div className="flex items-center gap-0.5 bg-amber-50 px-2 py-1 rounded-xl">
                    <Star className="w-3 h-3 fill-amber-500 text-amber-500" />
                    <span className="text-xs font-black text-amber-600">{rate.rating}</span>
                  </div>
                </div>
                {rate.comment && (
                  <p className="text-xs text-slate-600 font-semibold leading-relaxed pt-2 border-t border-slate-50">
                    {rate.comment}
                  </p>
                )}
              </div>
            ))
          ) : (
            <div className="text-center text-slate-400 py-10 text-xs font-semibold">
              Hozircha sharhlar yo'q
            </div>
          )}
        </div>
      )}

      {/* Cross-Store Cart Override Modal */}
      {pendingCartItem && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in" onClick={() => setPendingCartItem(null)}>
          <div className="bg-white rounded-3xl p-6 w-full max-w-sm shadow-xl space-y-4 animate-scale-up" onClick={e => e.stopPropagation()}>
            <div className="w-12 h-12 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center text-2xl mb-2">⚠️</div>
            <h3 className="font-black text-slate-800 text-lg leading-tight">Boshqa restoran!</h3>
            <p className="text-xs text-slate-500 font-medium leading-relaxed">
              Savatda boshqa oshxonaning taomlari bor. Savatni tozalab, shu taomni qo'shishni xohlaysizmi?
            </p>
            <div className="flex gap-2 pt-3">
              <button 
                onClick={() => setPendingCartItem(null)}
                className="flex-1 py-3 bg-slate-100 text-slate-600 font-bold rounded-2xl text-xs active:scale-95 transition"
              >
                Yo'q, qaytish
              </button>
              <button 
                onClick={() => {
                  forceClearAndAdd(pendingCartItem.item, pendingCartItem.storeInfo);
                  setPendingCartItem(null);
                  hapticFeedback('success');
                }}
                className="flex-1 py-3 bg-amber-500 text-white font-bold rounded-2xl text-xs active:scale-95 transition"
              >
                Ha, tozalash
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Product Variant / Option Selector Sheet */}
      <VariantSelectorModal
        isOpen={!!variantModalProduct}
        onClose={() => {
          setSelectedProductForVariant(null);
          setDismissedTargetId(targetProductId);
        }}
        product={variantModalProduct}
        onAdd={(variant) => {
          if (variantModalProduct) addToCart(variantModalProduct, variant);
        }}
      />
    </div>
  );
}
