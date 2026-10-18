import React, { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useCartStore } from '../store/cartStore';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { ArrowLeft, Search, X, ShoppingBag } from 'lucide-react';
import VariantSelectorModal from '../components/VariantSelectorModal';
import api from '../services/api';
import { logApiError } from '../lib/apiError';

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

export default function CategoryDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const { items: cartItems, addItem, forceClearAndAdd } = useCartStore();

  const [categories, setCategories] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<any>(null);
  const [pendingCartItem, setPendingCartItem] = useState<{item: any, storeInfo: any} | null>(null);

  useEffect(() => {
    const fetchCategoryData = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/catalog?category_id=${id}`);
        if (res.data.success) {
          setCategories(res.data.data.categories || []);
          setProducts(res.data.data.products || []);
        }
      } catch (err) {
        logApiError('Failed to load category:', err);
      } finally {
        setLoading(false);
      }
    };
    if (id) {
      fetchCategoryData();
    }
  }, [id]);

  const currentCategory = useMemo(() => {
    return categories.find(c => c.id === Number(id));
  }, [categories, id]);

  const subCategories = useMemo(() => {
    return categories.filter(c => c.parent_id === Number(id));
  }, [categories, id]);

  const filteredProducts = useMemo(() => {
    let result = products;
    if (selectedSubCategoryId) {
      result = result.filter(p => p.category_id === selectedSubCategoryId);
    }
    if (searchQuery) {
      result = result.filter(p => 
        p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (p.description && p.description.toLowerCase().includes(searchQuery.toLowerCase()))
      );
    }
    return result;
  }, [products, selectedSubCategoryId, searchQuery]);

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

  const handleConfirmClearCart = () => {
    if (pendingCartItem) {
      hapticFeedback('success');
      forceClearAndAdd(pendingCartItem.item, pendingCartItem.storeInfo);
      setPendingCartItem(null);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 pb-28 animate-fade-in flex flex-col">
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
            <h1 className="text-sm font-black text-slate-800 leading-none">
              {currentCategory ? currentCategory.name : 'Turkum'}
            </h1>
            <p className="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider">
              {filteredProducts.length} {language === 'uz' ? 'mahsulotlar' : 'продуктов'}
            </p>
          </div>
        </div>
      </div>

      {/* Subcategory Navigation & Search */}
      <div className="bg-white px-4 py-3 border-b border-slate-100 space-y-3">
        {/* Search */}
        <div className="relative">
          <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <Search className="w-4 h-4" />
          </span>
          <input 
            type="text"
            placeholder={language === 'uz' ? "Taomlarni qidirish..." : "Поиск блюд..."}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-9 py-2 bg-slate-50 border border-slate-100 rounded-xl text-xs placeholder-slate-400 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 transition-all text-slate-800"
          />
          {searchQuery && (
            <button 
              onClick={() => setSearchQuery('')}
              className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-650"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>

        {/* Subcategories */}
        {subCategories.length > 0 && (
          <div className="flex gap-2 overflow-x-auto scrollbar-hide py-1 pl-1 border-l-2 border-amber-500/35">
            <button
              onClick={() => { hapticFeedback('light'); setSelectedSubCategoryId(null); }}
              className={`px-3.5 py-1.5 rounded-xl text-[10px] font-bold whitespace-nowrap transition-all ${
                selectedSubCategoryId === null 
                  ? 'bg-amber-500 text-white shadow-sm' 
                  : 'bg-slate-50 text-slate-500 border border-slate-100'
              }`}
            >
              {language === 'uz' ? 'Barchasi' : 'Все'}
            </button>
            {subCategories.map((subCat) => {
              const isSelected = selectedSubCategoryId === subCat.id;
              return (
                <button
                  key={subCat.id}
                  onClick={() => {
                    hapticFeedback('light');
                    setSelectedSubCategoryId(isSelected ? null : subCat.id);
                  }}
                  className={`px-3.5 py-1.5 rounded-xl text-[10px] font-bold whitespace-nowrap transition-all ${
                    isSelected 
                      ? 'bg-amber-500 text-white shadow-sm' 
                      : 'bg-slate-50 text-slate-500 border border-slate-100 hover:bg-slate-100'
                  }`}
                >
                  {subCat.name}
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Products Display */}
      <div className="flex-1 p-4">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-24 gap-3">
            <div className="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Yuklanmoqda...</p>
          </div>
        ) : filteredProducts.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-24 px-6 text-center gap-4">
            <div className="w-16 h-16 bg-slate-100 border border-slate-200/50 rounded-full flex items-center justify-center text-slate-450">
              <ShoppingBag className="w-8 h-8 opacity-60" />
            </div>
            <div>
              <h3 className="text-sm font-black text-slate-800">
                {language === 'uz' ? 'Taomlar topilmadi' : 'Блюда не найдены'}
              </h3>
              <p className="text-[11px] text-slate-400 font-medium mt-1.5 max-w-[200px] mx-auto leading-relaxed">
                {language === 'uz' 
                  ? 'Ushbu turkumda hozircha mahsulotlar mavjud emas' 
                  : 'В этой категории пока нет товаров'
                }
              </p>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3.5 animate-fade-in">
            {filteredProducts.map((product) => {
              const matchStore = product.store;
              const cartQty = cartItems
                .filter(item => item.productId === product.id)
                .reduce((sum, item) => sum + item.quantity, 0);
              const price = getProductBasePrice(product);

              return (
                <div 
                  key={product.id} 
                  className="bg-white rounded-3xl p-3 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] flex flex-col justify-between cursor-pointer hover:shadow-md hover:-translate-y-0.5 active:scale-[0.97] transition-all duration-305 group"
                >
                  <div onClick={() => matchStore && navigate(`/store/${matchStore.uuid}`)}>
                    <div className="h-32 rounded-2xl overflow-hidden relative mb-2.5 bg-slate-50 flex items-center justify-center">
                      {product.image_url ? (
                        <img src={product.image_url} alt="" className="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                      ) : (
                        <span className="text-3xl">🍔</span>
                      )}
                      {cartQty > 0 && (
                        <div className="absolute top-2 right-2 bg-amber-500 text-white font-black text-[10px] w-6 h-6 rounded-full flex items-center justify-center border-2 border-white shadow-md">
                          {cartQty}
                        </div>
                      )}
                    </div>
                    {matchStore && (
                      <div className="flex items-center gap-1.5 mb-1.5">
                        <span className="text-xs">🏪</span>
                        <span className="text-[9px] font-black text-amber-500 tracking-wider uppercase truncate max-w-[110px]">{matchStore.name}</span>
                      </div>
                    )}
                    <h3 className="text-xs font-black text-slate-800 line-clamp-1 leading-tight">{product.name}</h3>
                    <p className="text-[10px] text-slate-400 font-medium line-clamp-2 mt-1 leading-relaxed">{product.description}</p>
                  </div>
                  <div className="flex items-center justify-between mt-3.5 pt-2 border-t border-slate-50">
                    <span className="text-[11px] font-black text-slate-800">{formatUzbekSum(price)}</span>
                    <button 
                      onClick={(e) => handleAddToCart(e, product)} 
                      className="bg-amber-50 hover:bg-amber-100 text-amber-600 p-2 rounded-full border border-amber-100 hover:border-amber-200 transition-all duration-200 active:scale-90"
                    >
                      <span className="text-xs font-bold px-1">+</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Variant Selector Bottom Sheet */}
      {selectedProductForVariant && (
        <VariantSelectorModal
          isOpen={!!selectedProductForVariant}
          product={selectedProductForVariant}
          onClose={() => setSelectedProductForVariant(null)}
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
            setSelectedProductForVariant(null);
          }}
        />
      )}

      {/* Cart Collision / Clear Warning Modal */}
      {pendingCartItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
          <div className="bg-white rounded-[32px] p-6 max-w-sm w-full shadow-2xl animate-scale-in text-center space-y-4">
            <div className="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center text-2xl mx-auto shadow-inner border border-amber-100">
              ⚠️
            </div>
            <h3 className="text-sm font-black text-slate-800 leading-snug">
              {language === 'uz' ? "Boshqa restorandan buyurtma?" : "Заказ из другого ресторана?"}
            </h3>
            <p className="text-xs text-slate-500 font-medium leading-relaxed">
              {language === 'uz' 
                ? "Sizning savatingizda boshqa restoranning mahsulotlari bor. Yangi restorandan qo'shish uchun savatni tozalash kerak." 
                : "В вашей корзине уже есть товары из другого ресторана. Очистить корзину для добавления?"}
            </p>
            <div className="flex gap-3 pt-2">
              <button 
                onClick={() => { hapticFeedback('light'); setPendingCartItem(null); }}
                className="flex-1 bg-slate-50 hover:bg-slate-100 text-slate-700 font-black text-xs py-3 rounded-xl transition"
              >
                {language === 'uz' ? "Bekor qilish" : "Отмена"}
              </button>
              <button 
                onClick={handleConfirmClearCart}
                className="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-3 rounded-xl transition shadow-lg shadow-amber-500/20"
              >
                {language === 'uz' ? "Savatni tozalash" : "Очистить"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
