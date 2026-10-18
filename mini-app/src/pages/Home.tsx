import React, { useState, useEffect, useMemo } from 'react';
import { useCartStore } from '../store/cartStore';
import { useAuthStore } from '../store/authStore';
import { useAddressStore } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { ErrorFallback } from '../App';
import api from '../services/api';
import { Search, X, MapPin, Heart, Star, Plus, Bell } from 'lucide-react';
import HeroPromoSlider from '../components/HeroPromoSlider';
import { useNavigate } from 'react-router-dom';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { useLanguageStore } from '../store/languageStore';

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

export default function Home() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  useAuthStore(); // imported for completeness if needed but removed unused variables
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];

  const { items: cartItems, addItem, forceClearAndAdd } = useCartStore();
  const [pendingCartItem, setPendingCartItem] = useState<{item: any, storeInfo: any} | null>(null);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<any>(null);

  const [searchQuery, setSearchQuery] = useState('');
  const [categories, setCategories] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [promos, setPromos] = useState<any[]>([]);
  const [highlightProduct, setHighlightProduct] = useState<any>(null);
  const [stores, setStores] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  
  // Favorites mock state
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const params: any = {};
        if (activeAddress) {
          if (activeAddress.lat) params.lat = activeAddress.lat;
          if (activeAddress.lng) params.lng = activeAddress.lng;
          if (activeAddress.region_id) params.region_id = activeAddress.region_id;
          if (activeAddress.district_id) params.district_id = activeAddress.district_id;
        }

        const [catalogRes, storesRes] = await Promise.all([
          api.get('/catalog', { params }),
          api.get('/stores', { params })
        ]);
        
        if (catalogRes.data.success) {
          setCategories(catalogRes.data.data.categories);
          setProducts(catalogRes.data.data.products);
          setPromos(catalogRes.data.data.promos || []);
          setHighlightProduct(catalogRes.data.data.highlight_product || null);
        }
        if (storesRes.data.success) {
          setStores(storesRes.data.data);
        }
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [selectedAddressId, addresses]);

  const toggleFavoriteStore = (e: React.MouseEvent, uuid: string) => {
    e.stopPropagation();
    hapticFeedback('light');
    setFavoriteStores(prev => 
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

  const handleAddToCart = (e: React.MouseEvent, product: any) => {
    e.stopPropagation();
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

    const storeInfo = product.store ? { 
      name: product.store.name, 
      address: product.store.address || 'Asosiy filial' 
    } : undefined;

    const isSuccess = addItem(cartItem, storeInfo);
    if (!isSuccess) {
      setPendingCartItem({ item: cartItem, storeInfo });
    }
  };

  const parentCategories = useMemo(() => {
    return categories.filter(c => !c.parent_id);
  }, [categories]);

  const searchedProducts = useMemo(() => {
    let filtered = products;
    if (searchQuery) {
      filtered = filtered.filter(product => {
        const matchStore = stores.find(s => s.id === product.store_id);
        return product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
               (matchStore && matchStore.name.toLowerCase().includes(searchQuery.toLowerCase())) ||
               (product.description && product.description.toLowerCase().includes(searchQuery.toLowerCase()));
      });
    }
    return filtered;
  }, [searchQuery, products, stores]);

  if (error) return <ErrorFallback error={error?.message || "Failed to load"} />;
  if (loading) {
    return (
      <div className="space-y-6 pb-24 bg-white min-h-screen">
        {/* Header Skeleton */}
        <div className="bg-white px-4 pt-4 pb-5 rounded-b-[2.5rem] shadow-md shadow-slate-100/30 border-b border-slate-100">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-2xl bg-slate-100 animate-pulse"></div>
              <div className="space-y-1.5">
                <div className="h-4 w-20 bg-slate-100 rounded animate-pulse"></div>
                <div className="h-3 w-32 bg-slate-100 rounded animate-pulse"></div>
              </div>
            </div>
            <div className="w-10 h-10 rounded-2xl bg-slate-100 animate-pulse"></div>
          </div>
          <div className="w-full h-11 bg-slate-100 rounded-2xl animate-pulse"></div>
        </div>

        {/* Promo Slider Skeleton */}
        <div className="mx-4 h-40 bg-slate-100 rounded-3xl animate-pulse"></div>

        {/* Categories Skeleton */}
        <div className="px-4 space-y-3">
          <div className="h-3.5 w-16 bg-slate-100 rounded animate-pulse"></div>
          <div className="flex gap-4 overflow-x-auto scrollbar-hide">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex flex-col items-center gap-2 shrink-0">
                <div className="w-16 h-16 rounded-full bg-slate-100 animate-pulse"></div>
                <div className="h-3 w-12 bg-slate-100 rounded animate-pulse"></div>
              </div>
            ))}
          </div>
        </div>

        {/* Oshxonalar Skeleton */}
        <div className="px-4 space-y-3">
          <div className="flex justify-between items-center">
            <div className="h-3.5 w-36 bg-slate-100 rounded animate-pulse"></div>
            <div className="h-3.5 w-12 bg-slate-100 rounded animate-pulse"></div>
          </div>
          <div className="flex gap-4 overflow-x-auto scrollbar-hide">
            {[1, 2, 3].map((i) => (
              <div key={i} className="bg-white rounded-3xl border border-slate-100 p-4 shrink-0 w-48 flex flex-col justify-between gap-4">
                <div className="flex items-center gap-2.5">
                  <div className="w-12 h-12 bg-slate-100 rounded-2xl animate-pulse shrink-0"></div>
                  <div className="space-y-1.5 min-w-0 flex-1">
                    <div className="h-3 bg-slate-100 rounded animate-pulse w-3/4"></div>
                    <div className="h-2 bg-slate-100 rounded animate-pulse w-1/2"></div>
                  </div>
                </div>
                <div className="space-y-1.5">
                  <div className="h-2.5 bg-slate-100 rounded animate-pulse w-full"></div>
                  <div className="h-2.5 bg-slate-100 rounded animate-pulse w-5/6"></div>
                </div>
                <div className="flex justify-between items-center pt-2.5 border-t border-slate-50">
                  <div className="h-3 w-8 bg-slate-100 rounded animate-pulse"></div>
                  <div className="h-3 w-14 bg-slate-100 rounded animate-pulse"></div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Recommended Dishes Skeleton */}
        <div className="px-4 space-y-3">
          <div className="h-3.5 w-28 bg-slate-100 rounded animate-pulse"></div>
          <div className="grid grid-cols-2 gap-3.5">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="bg-white rounded-3xl p-3 border border-slate-100 flex flex-col justify-between gap-3">
                <div className="h-32 rounded-2xl bg-slate-100 animate-pulse"></div>
                <div className="space-y-1.5">
                  <div className="h-3 bg-slate-100 rounded animate-pulse w-3/4"></div>
                  <div className="h-2.5 bg-slate-100 rounded animate-pulse w-full"></div>
                </div>
                <div className="flex justify-between items-center pt-2 border-t border-slate-50">
                  <div className="h-3 w-12 bg-slate-100 rounded animate-pulse"></div>
                  <div className="w-8 h-8 rounded-full bg-slate-100 animate-pulse"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  // Bugungi Osh (Highlight product from API)
  const bugungiOshProduct = highlightProduct;

  const displayAddress = activeAddress ? activeAddress.address : "Manzilni qo'shish";

  return (
    <div className="space-y-6 pb-24 animate-fade-in bg-white min-h-screen">
      {/* Brand Header & Location Badge */}
      <div className="bg-white px-4 pt-4 pb-5 rounded-b-[2.5rem] shadow-md shadow-slate-100/30 border-b border-slate-100 relative z-10">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-2xl bg-amber-500 flex items-center justify-center text-white font-black text-xl shadow-md">
              TF
            </div>
            <div className="min-w-0">
              <h1 className="text-lg font-black text-slate-800 tracking-tight leading-none">RIZQIM</h1>
              <button 
                onClick={() => navigate('/profile')}
                className="flex items-center gap-1 text-[10px] text-slate-400 font-bold mt-1 text-left hover:text-amber-500 transition truncate max-w-[190px]"
              >
                <MapPin className="w-3 h-3 shrink-0" /> <span className="truncate">{displayAddress}</span>
              </button>
            </div>
          </div>
          
          <button 
            onClick={() => { hapticFeedback('light'); navigate('/notifications'); }}
            className="w-10 h-10 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-100 active:scale-95 transition relative shrink-0"
          >
            <Bell className="w-4.5 h-4.5" />
            <span className="absolute top-2.5 right-2.5 w-2 h-2 bg-rose-500 rounded-full border border-white"></span>
          </button>
        </div>

        {/* Dynamic Global Search */}
        <div className="relative">
          <span className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
            <Search className="w-4 h-4" />
          </span>
          <input 
            type="text"
            placeholder="Taom yoki oshxonalarni qidirish..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-11 pr-10 py-3 bg-slate-50 border border-slate-100 rounded-2xl text-xs placeholder-slate-400 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 transition-all text-slate-800"
          />
          {searchQuery && (
            <button 
              onClick={() => setSearchQuery('')}
              className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Hero Promo Banner Slider */}
      <HeroPromoSlider promos={promos} />

      <div className="px-4 space-y-4">
        <div>
          <h2 className="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-3">Turkumlar</h2>
          <div className="flex gap-4 overflow-x-auto scrollbar-hide pb-1 -mx-4 px-4">
            {parentCategories.map((cat) => {
              const catImage = cat.image_url || `https://ui-avatars.com/api/?name=${cat.name}&background=fcd34d&color=fff&size=120`;
              return (
                <div 
                  key={cat.id} 
                  onClick={() => {
                    hapticFeedback('light');
                    navigate(`/category/${cat.id}`);
                  }}
                  className="flex flex-col items-center gap-2 cursor-pointer shrink-0 group active:scale-[0.93] transition-all duration-200"
                >
                  <div className="w-16 h-16 rounded-full overflow-hidden border-2 shadow-md shadow-slate-100 bg-white p-0.5 flex items-center justify-center transition-transform group-hover:scale-105 duration-200 border-slate-100">
                    <img 
                      src={catImage} 
                      alt={cat.name} 
                      className="w-full h-full object-cover rounded-full" 
                    />
                  </div>
                  <span className="text-[10px] font-black text-center max-w-[70px] truncate text-slate-600">
                    {cat.name}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Uzbek Market Special: "Bugungi Toshkent Oshi" widget */}
      {bugungiOshProduct && !searchQuery && (
        <div className="mx-4 p-4 rounded-3xl bg-amber-50 border border-amber-200 flex items-center justify-between gap-4 shadow-sm">
          <div className="flex-1 min-w-0">
            <span className="bg-amber-200 text-amber-800 text-[8px] font-black tracking-widest uppercase px-2 py-0.5 rounded">
              BUGUNGI MILLIY TAVSIYA 🍛
            </span>
            <h3 className="text-sm font-black text-slate-800 mt-1 line-clamp-1">{bugungiOshProduct.name}</h3>
            <p className="text-[10px] text-slate-500 mt-0.5 line-clamp-1">{bugungiOshProduct.description}</p>
            <div className="flex items-center gap-2 mt-2">
              <span className="text-xs font-black text-slate-800">{formatUzbekSum(getProductBasePrice(bugungiOshProduct))}</span>
              <button 
                onClick={(e) => handleAddToCart(e, bugungiOshProduct)}
                className="bg-amber-500 hover:bg-amber-600 text-white text-[9px] font-black px-2.5 py-1 rounded-lg transition"
              >
                Buyurtma qilish
              </button>
            </div>
          </div>
          <div className="w-20 h-20 shrink-0 bg-slate-200 rounded-2xl overflow-hidden border border-amber-100 shadow">
             {bugungiOshProduct.image_url ? (
               <img src={bugungiOshProduct.image_url} alt="" className="w-full h-full object-cover" />
             ) : (
               <div className="w-full h-full bg-amber-100 flex items-center justify-center text-xl">🍽️</div>
             )}
          </div>
        </div>
      )}

      {/* Partner Cafes & Restaurants */}
      <div className="px-4">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-[10px] font-black uppercase tracking-wider text-slate-400">Oshxonalar & Restoranlar</h2>
          <button 
            onClick={() => { hapticFeedback('light'); navigate('/stores'); }}
            className="text-[10px] font-black text-amber-500 hover:text-amber-600 tracking-wider uppercase active:scale-95 transition"
          >
            {language === 'uz' ? "Barchasini ko'rish" : "Показать все"}
          </button>
        </div>
        <div className="flex gap-4 overflow-x-auto scrollbar-hide pb-2 -mx-4 px-4">
          {stores.map((store) => {
            const isFav = favoriteStores.includes(store.uuid);
            
            const hasLogo = store.logo_url && store.logo_url.trim() !== "";
            const initials = store.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase();

            return (
              <div 
                key={store.id} 
                onClick={() => navigate(`/store/${store.uuid}`)}
                className="bg-white rounded-3xl border border-slate-100 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.02)] shrink-0 w-48 cursor-pointer flex flex-col justify-between hover:shadow-md active:scale-[0.97] transition-all duration-300 relative group"
              >
                {/* Favorite Heart Trigger */}
                <button 
                  onClick={(e) => toggleFavoriteStore(e, store.uuid)}
                  className="absolute top-3 right-3 p-1.5 bg-white/80 backdrop-blur-md rounded-full shadow-sm z-10 active:scale-75 transition text-slate-300"
                >
                  <Heart className={`w-3.5 h-3.5 ${isFav ? 'fill-rose-500 text-rose-500' : ''}`} />
                </button>

                <div>
                  <div className="flex items-center gap-2.5 mb-2.5">
                    {hasLogo ? (
                      <div className="w-12 h-12 bg-slate-50 rounded-2xl overflow-hidden shadow-inner border border-slate-100 shrink-0">
                        <img src={store.logo_url} alt="" className="w-full h-full object-cover" />
                      </div>
                    ) : (
                      <div className="w-12 h-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center font-black text-sm tracking-tight border border-amber-200 shrink-0">
                        {initials}
                      </div>
                    )}
                    <div className="min-w-0">
                      <h3 className="text-xs font-black text-slate-800 truncate leading-snug flex items-center gap-1">
                        {store.name}
                        {store.is_premium && <span className="text-[9px]" title="Premium Restoran">👑</span>}
                      </h3>
                      <p className="text-[9px] font-extrabold text-slate-400 mt-0.5">{store.delivery_time || "20-30 daqiqa"}</p>
                    </div>
                  </div>
                  <p className="text-[10px] text-slate-400 font-medium line-clamp-2 leading-relaxed">{store.description || "Mazali taomlar"}</p>
                </div>
                <div className="flex items-center justify-between mt-3.5 pt-2.5 border-t border-slate-50">
                  <span className="text-[10px] font-black text-amber-500 flex items-center gap-0.5">
                    <Star className="w-3 h-3 fill-amber-500" /> {store.rating || "4.5"}
                  </span>
                  <span className="text-[9px] text-slate-400 font-bold">Min: {formatUzbekSum(store.min_order !== undefined && store.min_order !== null ? store.min_order : 30000)}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Recommended Mixed Dishes Grid */}
      <div className="px-4">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-[10px] font-black uppercase tracking-wider text-slate-400">Siz uchun tavsiyalar</h2>
          {searchQuery && (
            <span className="text-[10px] text-slate-400 font-bold">{searchedProducts.length} ta natija</span>
          )}
        </div>

        <div className="grid grid-cols-2 gap-3.5">
          {searchedProducts.map((product) => {
            const matchStore = product.store;
            const cartQty = cartItems
              .filter(item => item.productId === product.id)
              .reduce((sum, item) => sum + item.quantity, 0);

            return (
              <div 
                key={product.id} 
                className="bg-white rounded-3xl p-3 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] flex flex-col justify-between cursor-pointer hover:shadow-md hover:-translate-y-0.5 active:scale-[0.97] transition-all duration-300 group"
              >
                <div onClick={() => navigate(`/store/${product.store?.uuid}`)}>
                  <div className="h-32 rounded-2xl overflow-hidden relative mb-2.5 bg-slate-50 flex items-center justify-center">
                    {product.image_url ? (
                      <img src={product.image_url} alt="" className="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                    ) : (
                      <span className="text-3xl">🍔</span>
                    )}
                    {cartQty > 0 && (
                      <div className="absolute top-2 right-2 bg-amber-500 text-white font-black text-[10px] w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-md animate-pulse">
                        {cartQty}
                      </div>
                    )}
                  </div>
                  {matchStore && (
                    <div className="flex items-center gap-1.5 mb-1.5">
                      <span className="text-xs">🏪</span>
                      <span className="text-[9px] font-black text-amber-500 tracking-wider uppercase truncate">{matchStore.name}</span>
                    </div>
                  )}
                  <h3 className="text-xs font-black text-slate-800 line-clamp-1 leading-tight">{product.name}</h3>
                  <p className="text-[10px] text-slate-400 font-medium line-clamp-2 mt-1 leading-relaxed">{product.description}</p>
                </div>
                <div className="flex items-center justify-between mt-3.5 pt-2 border-t border-slate-50">
                  <span className="text-[11px] font-black text-slate-800">{formatUzbekSum(getProductBasePrice(product))}</span>
                  <button 
                    onClick={(e) => handleAddToCart(e, product)} 
                    className="bg-amber-50 hover:bg-amber-100 text-amber-600 p-2 rounded-full border border-amber-100 hover:border-amber-200 transition-all duration-200 active:scale-90"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>

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

          const storeInfo = selectedProductForVariant.store ? { 
            name: selectedProductForVariant.store.name, 
            address: selectedProductForVariant.store.address || 'Asosiy filial' 
          } : undefined;

          const isSuccess = addItem(cartItem, storeInfo);
          if (!isSuccess) {
            setPendingCartItem({ item: cartItem, storeInfo });
          }
        }}
      />
    </div>
  );
}
