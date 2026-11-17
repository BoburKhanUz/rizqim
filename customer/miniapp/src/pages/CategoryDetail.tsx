import { useState, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useCartStore } from '../store/cartStore';
import { useLanguageStore } from '../store/languageStore';
import { selectionChanged } from '../lib/twa';
import { useCardToCart, useFeedCards } from '../hooks/useMarketplace';
import { useAddressStore } from '../store/useAddressStore';
import { getLocationParams } from '../lib/location';
import { cardToProduct, isAcceptingOrders, NOT_ACCEPTING_TEXT, searchCards } from '../lib/marketplace';
import { ShoppingBag } from 'lucide-react';
import ProductSheet from '../components/ProductSheet';
import { EmptyState, InlineError, PageHeader, cx } from '../components/ui';
import { ProductCard } from '../components/catalog/ProductCard';
import { ReplaceCartDialog } from '../components/catalog/ReplaceCartDialog';
import { SearchField } from '../components/catalog/SearchField';
import type { Category, ProductCardItem } from '../types';

const NO_CATEGORIES: Category[] = [];
const NO_CARDS: ProductCardItem[] = [];

const CHIP = 'min-h-11 shrink-0 cursor-pointer whitespace-nowrap rounded-control px-4 text-body font-medium transition-colors';

export default function CategoryDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const cartItems = useCartStore((state) => state.items);
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];
  // Wave M2: the category's dishes of the stores serving the address (as the home feed), as light cards
  const location = useMemo(() => getLocationParams(activeAddress), [activeAddress]);
  const feed = useFeedCards(location, id, Boolean(id));
  const cart = useCardToCart();
  const categories = feed.data?.categories ?? NO_CATEGORIES;
  const products = feed.data?.products ?? NO_CARDS;
  const loading = feed.isPending;
  const error = feed.error;

  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  const currentCategory = useMemo(() => {
    return categories.find(c => c.id === Number(id));
  }, [categories, id]);

  const subCategories = useMemo(() => {
    return categories.filter(c => c.parent_id === Number(id));
  }, [categories, id]);

  const filteredProducts = useMemo(() => {
    const result = selectedSubCategoryId ? products.filter(p => p.category_id === selectedSubCategoryId) : products;
    return searchCards(result, searchQuery);
  }, [products, selectedSubCategoryId, searchQuery]);

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
        <InlineError onRetry={() => void feed.refetch()} />
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
          {filteredProducts.map((card) => {
            const matchStore = card.store;
            const cartQty = cartItems
              .filter(item => item.productId === card.id)
              .reduce((sum, item) => sum + item.quantity, 0);

            return (
              <ProductCard
                key={card.id}
                product={cardToProduct(card)}
                storeName={matchStore?.name}
                priceUnit={card.unit}
                cartQty={cartQty}
                onOpen={matchStore ? () => navigate(`/store/${matchStore.uuid}`) : undefined}
                onAdd={() => void cart.start(card)}
                busy={cart.loadingId === card.id}
                blockedText={isAcceptingOrders(matchStore) ? undefined : NOT_ACCEPTING_TEXT}
              />
            );
          })}
        </div>
      )}

      {/* The product sheet: variant, modifiers, weight */}
      {cart.sheetProduct && (
        <ProductSheet
          isOpen
          product={cart.sheetProduct}
          availability={cart.availability}
          onClose={cart.closeSheet}
          onAdd={cart.addFromSheet}
        />
      )}

      <ReplaceCartDialog pending={cart.pending} onClose={cart.clearPending} />
    </div>
  );
}
