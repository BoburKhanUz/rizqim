import { useState, useEffect, useMemo } from 'react';
import { useCartStore, type PendingCartItem } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { useCanAddToCart } from '../hooks/useCanAddToCart';
import { Button, EmptyState, InlineError, PageHeader, ProductImage, Section, buttonClasses } from '../components/ui';
import api from '../services/api';
import { MapPin, Search } from 'lucide-react';
import HeroPromoSlider from '../components/HeroPromoSlider';
import { Link, useNavigate } from 'react-router-dom';
import VariantSelectorModal from '../components/VariantSelectorModal';
import { NotificationBell } from '../components/catalog/NotificationBell';
import { ProductCard } from '../components/catalog/ProductCard';
import { ReplaceCartDialog } from '../components/catalog/ReplaceCartDialog';
import { SearchField } from '../components/catalog/SearchField';
import { StoreCard } from '../components/catalog/StoreCard';
import { useLanguageStore } from '../store/languageStore';
import { formatMoney } from '../lib/format';
import { buildCartItem, getProductBasePrice, getStoreInfo } from '../lib/catalog';
import { getLocationParams } from '../lib/location';
import type { ApiResponse, Category, FeedCatalog, Product, ProductVariant, Promo, StoreListItem } from '../types';

export default function Home() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];

  const { items: cartItems, addItem } = useCartStore();
  const [pendingCartItem, setPendingCartItem] = useState<PendingCartItem | null>(null);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<Product | null>(null);

  const [searchQuery, setSearchQuery] = useState('');
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [promos, setPromos] = useState<Promo[]>([]);
  const [highlightProduct, setHighlightProduct] = useState<Product | null>(null);
  const [stores, setStores] = useState<StoreListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by "Qayta urinish" after a failed load

  // Favourite restaurants of this visit (not stored anywhere yet)
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        const params = getLocationParams(activeAddress);

        const [catalogRes, storesRes] = await Promise.all([
          api.get<ApiResponse<FeedCatalog>>('/catalog', { params }),
          api.get<ApiResponse<StoreListItem[]>>('/stores', { params })
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
  }, [activeAddress, reloadKey]);

  const toggleFavoriteStore = (uuid: string) => {
    hapticFeedback('light');
    setFavoriteStores(prev =>
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

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

  const query = searchQuery.trim().toLowerCase();
  const searching = query !== '';
  const shownStores = searching ? stores.filter((store) => store.name.toLowerCase().includes(query)) : stores;

  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pb-4 pt-2">
        <PageHeader title="Rizqim" />
        <InlineError onRetry={() => setReloadKey((key) => key + 1)} />
      </div>
    );
  }
  if (loading) {
    return (
      <div className="flex flex-col gap-5 px-4 pb-4 pt-2" aria-busy="true">
        <div className="flex items-center justify-between gap-3">
          <div className="space-y-2">
            <div className="h-6 w-24 animate-pulse rounded-control bg-line" />
            <div className="h-4 w-40 animate-pulse rounded-control bg-line" />
          </div>
          <div className="size-11 animate-pulse rounded-full bg-line" />
        </div>
        <div className="h-11 animate-pulse rounded-control bg-line" />
        <div className="h-44 animate-pulse rounded-card bg-line" />
        <div className="flex gap-3 overflow-hidden">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="flex shrink-0 flex-col items-center gap-2">
              <div className="size-16 animate-pulse rounded-full bg-line" />
              <div className="h-3 w-12 animate-pulse rounded-control bg-line" />
            </div>
          ))}
        </div>
        <div className="grid grid-cols-2 gap-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="space-y-2 rounded-card border border-line bg-surface p-3">
              <div className="aspect-[4/3] animate-pulse rounded-control bg-surface-2" />
              <div className="h-4 w-3/4 animate-pulse rounded-control bg-surface-2" />
              <div className="h-3 w-full animate-pulse rounded-control bg-surface-2" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  // The highlighted dish of the day (from the API)
  const featuredProduct = highlightProduct;

  const displayAddress = activeAddress ? activeAddress.address : "Manzilni qo'shish";

  return (
    <div className="flex flex-col gap-5 px-4 pb-4 pt-2 animate-fade-in">
      {/* Brand and delivery address */}
      <header className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <h1 className="text-title font-bold text-fg">Rizqim</h1>
          <button
            type="button"
            onClick={() => navigate('/profile')}
            className="-ml-1 flex min-h-11 max-w-full cursor-pointer items-center gap-1 rounded-control px-1 text-left text-footnote text-fg-muted"
          >
            <MapPin aria-hidden="true" className="size-4 shrink-0" />
            <span className="truncate">{displayAddress}</span>
          </button>
        </div>
        <NotificationBell />
      </header>

      <SearchField value={searchQuery} onChange={setSearchQuery} placeholder="Taom yoki oshxona qidirish" />

      {/* While searching only the results are shown, so they (and "nothing found") are right under the field */}
      {!searching && <HeroPromoSlider promos={promos} />}

      {!searching && parentCategories.length > 0 && (
        <Section title="Turkumlar">
          <div className="scrollbar-hide -mx-4 flex gap-1 overflow-x-auto px-3">
            {parentCategories.map((cat) => (
              <Link
                key={cat.id}
                to={`/category/${cat.id}`}
                onClick={() => hapticFeedback('light')}
                className="flex w-20 shrink-0 flex-col items-center gap-2 rounded-card px-1 py-1.5 transition-transform motion-safe:active:scale-95"
              >
                <ProductImage src={cat.image_url} className="size-16 rounded-full border border-line" />
                <span className="line-clamp-2 w-full text-center text-caption font-medium text-fg">{cat.name}</span>
              </Link>
            ))}
          </div>
        </Section>
      )}

      {/* The dish of the day */}
      {featuredProduct && !searchQuery && (
        <section aria-label="Bugungi tavsiya" className="flex items-center gap-4 rounded-card bg-brand-soft p-4">
          <div className="flex min-w-0 flex-1 flex-col items-start gap-1">
            <p className="text-caption font-bold text-brand-soft-fg">Bugungi tavsiya</p>
            <h2 className="line-clamp-2 text-headline font-bold text-fg">{featuredProduct.name}</h2>
            {featuredProduct.description && <p className="line-clamp-1 text-footnote text-fg-muted">{featuredProduct.description}</p>}
            <p className="text-body font-bold text-fg">{formatMoney(getProductBasePrice(featuredProduct))}</p>
            <Button className="mt-1" onClick={() => handleAddToCart(featuredProduct)}>
              Buyurtma qilish
            </Button>
          </div>
          <ProductImage src={featuredProduct.image_url} className="size-24 rounded-card" />
        </section>
      )}

      {/* Restaurants */}
      {shownStores.length > 0 && (
        <Section
          title="Oshxonalar va restoranlar"
          action={
            <Link
              to="/stores"
              onClick={() => hapticFeedback('light')}
              className={`${buttonClasses({ variant: 'ghost' })} -my-2`}
            >
              {uz ? "Barchasini ko'rish" : "Показать все"}
            </Link>
          }
        >
          <div className="scrollbar-hide -mx-4 flex gap-3 overflow-x-auto px-4 pb-1">
            {shownStores.map((store) => (
              <StoreCard
                key={store.id}
                store={store}
                variant="compact"
                isFavorite={favoriteStores.includes(store.uuid)}
                onToggleFavorite={() => toggleFavoriteStore(store.uuid)}
              />
            ))}
          </div>
        </Section>
      )}

      {/* Dishes of all restaurants */}
      <Section
        title={searching ? 'Taomlar' : 'Siz uchun tavsiyalar'}
        action={searching ? <span className="text-footnote text-fg-muted">{searchedProducts.length} ta natija</span> : undefined}
      >
        {searchedProducts.length === 0 && searchQuery ? (
          <EmptyState icon={Search} title="Hech narsa topilmadi" text="Boshqa so'z bilan qidirib ko'ring" />
        ) : (
          <div className="grid grid-cols-2 gap-3">
            {searchedProducts.map((product) => {
              const cartQty = cartItems
                .filter(item => item.productId === product.id)
                .reduce((sum, item) => sum + item.quantity, 0);

              return (
                <ProductCard
                  key={product.id}
                  product={product}
                  cartQty={cartQty}
                  onOpen={product.store?.uuid ? () => navigate(`/store/${product.store?.uuid}`) : undefined}
                  onAdd={() => handleAddToCart(product)}
                />
              );
            })}
          </div>
        )}
      </Section>

      <ReplaceCartDialog pending={pendingCartItem} onClose={() => setPendingCartItem(null)} />

      {/* Product Variant / Option Selector Sheet */}
      <VariantSelectorModal
        isOpen={!!selectedProductForVariant}
        onClose={() => setSelectedProductForVariant(null)}
        product={selectedProductForVariant}
        onAdd={(variant) => {
          if (selectedProductForVariant) addToCart(selectedProductForVariant, variant);
        }}
      />
    </div>
  );
}
