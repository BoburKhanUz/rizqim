import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useCartStore, type PendingCartItem } from '../store/cartStore';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { useCanAddToCart } from '../hooks/useCanAddToCart';
import { ShoppingBag } from 'lucide-react';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { EmptyState, InlineError, PageHeader, cx } from '../components/ui';
import { ProductCard } from '../components/catalog/ProductCard';
import { ReplaceCartDialog } from '../components/catalog/ReplaceCartDialog';
import { SearchField } from '../components/catalog/SearchField';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { buildCartItem, getStoreInfo } from '../lib/catalog';
import type { ApiResponse, Category, FeedCatalog, Product, ProductVariant } from '../types';

const CHIP = 'min-h-11 shrink-0 cursor-pointer whitespace-nowrap rounded-control px-4 text-body font-medium transition-colors';

export default function CategoryDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const { items: cartItems, addItem } = useCartStore();

  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by "Qayta urinish" after a failed load

  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  const [selectedProductForVariant, setSelectedProductForVariant] = useState<Product | null>(null);
  const [pendingCartItem, setPendingCartItem] = useState<PendingCartItem | null>(null);

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    const fetchCategoryData = async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await api.get<ApiResponse<FeedCatalog>>(`/catalog?category_id=${id}`);
        if (res.data.success && !cancelled) {
          setCategories(res.data.data.categories || []);
          setProducts(res.data.data.products || []);
        }
      } catch (err) {
        logApiError('Failed to load category:', err);
        if (!cancelled) setError(err);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchCategoryData();
    return () => {
      cancelled = true;
    };
  }, [id, reloadKey]);

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

  const canAddVariant = useCanAddToCart();

  // Puts one unit into the cart (unless the stock rule blocks it); another store's items in the cart ask the customer first
  const addToCart = (product: Product, variant: ProductVariant | undefined) => {
    const cartItem = buildCartItem(product, variant);
    if (!canAddVariant(variant, cartItem.id)) return;
    const storeInfo = getStoreInfo(product.store);

    const isSuccess = addItem(cartItem, storeInfo);
    if (!isSuccess) {
      setPendingCartItem({ item: cartItem, storeInfo });
    }
  };

  const handleAddToCart = (product: Product) => {
    hapticFeedback('medium');

    if (product.variants && product.variants.length > 1) {
      setSelectedProductForVariant(product);
      return;
    }

    addToCart(product, product.variants?.[0]);
  };

  const selectSubCategory = (subCategoryId: number | null) => {
    selectionChanged();
    setSelectedSubCategoryId(subCategoryId);
  };

  return (
    <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
      <PageHeader
        back
        title={currentCategory ? currentCategory.name : 'Turkum'}
        subtitle={loading || error ? undefined : `${filteredProducts.length} ${uz ? 'mahsulot' : 'продуктов'}`}
      />

      <SearchField
        value={searchQuery}
        onChange={setSearchQuery}
        placeholder={uz ? 'Taomlarni qidirish' : 'Поиск блюд'}
      />

      {subCategories.length > 0 && (
        <div className="scrollbar-hide -mx-4 flex gap-2 overflow-x-auto px-4">
          {[{ id: null, name: uz ? 'Barchasi' : 'Все' }, ...subCategories].map((subCat) => {
            const isSelected = selectedSubCategoryId === subCat.id;
            return (
              <button
                key={subCat.id ?? 'all'}
                type="button"
                aria-pressed={isSelected}
                onClick={() => selectSubCategory(subCat.id === null || isSelected ? null : subCat.id)}
                className={cx(CHIP, isSelected ? 'bg-brand text-on-brand' : 'border border-line bg-surface text-fg')}
              >
                {subCat.name}
              </button>
            );
          })}
        </div>
      )}

      {error ? (
        <InlineError onRetry={() => setReloadKey((key) => key + 1)} />
      ) : loading ? (
        <div className="grid grid-cols-2 gap-3" aria-busy="true">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="space-y-2 rounded-card border border-line bg-surface p-3">
              <div className="aspect-[4/3] animate-pulse rounded-control bg-surface-2" />
              <div className="h-4 w-3/4 animate-pulse rounded-control bg-surface-2" />
              <div className="h-3 w-full animate-pulse rounded-control bg-surface-2" />
            </div>
          ))}
        </div>
      ) : filteredProducts.length === 0 ? (
        <EmptyState
          icon={ShoppingBag}
          title={uz ? 'Taomlar topilmadi' : 'Блюда не найдены'}
          text={uz ? 'Ushbu turkumda hozircha mahsulotlar mavjud emas' : 'В этой категории пока нет товаров'}
        />
      ) : (
        <div className="grid grid-cols-2 gap-3 animate-fade-in">
          {filteredProducts.map((product) => {
            const matchStore = product.store;
            const cartQty = cartItems
              .filter(item => item.productId === product.id)
              .reduce((sum, item) => sum + item.quantity, 0);

            return (
              <ProductCard
                key={product.id}
                product={product}
                cartQty={cartQty}
                onOpen={matchStore ? () => navigate(`/store/${matchStore.uuid}`) : undefined}
                onAdd={() => handleAddToCart(product)}
              />
            );
          })}
        </div>
      )}

      {/* Variant Selector Bottom Sheet */}
      {selectedProductForVariant && (
        <VariantSelectorModal
          isOpen={!!selectedProductForVariant}
          product={selectedProductForVariant}
          onClose={() => setSelectedProductForVariant(null)}
          onAdd={(variant) => {
            addToCart(selectedProductForVariant, variant);
            setSelectedProductForVariant(null);
          }}
        />
      )}

      <ReplaceCartDialog pending={pendingCartItem} onClose={() => setPendingCartItem(null)} />
    </div>
  );
}
