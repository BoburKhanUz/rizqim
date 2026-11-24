import { useState, useMemo } from 'react';
import { useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import { hapticFeedback } from '../lib/telegram';
import { BrandLogo, Button, EmptyState, InlineError, PageHeader, ProductImage, Section, buttonClasses } from '../components/ui';
import { MapPin, Search, Store } from 'lucide-react';
import HeroPromoSlider from '../components/HeroPromoSlider';
import { Link, useNavigate } from 'react-router-dom';
import ProductSheet from '../components/ProductSheet';
import { NotificationBell } from '../components/catalog/NotificationBell';
import { ProductCard } from '../components/catalog/ProductCard';
import { ReplaceCartDialog } from '../components/catalog/ReplaceCartDialog';
import { SearchField } from '../components/catalog/SearchField';
import { StoreCard } from '../components/catalog/StoreCard';
import { useLanguageStore } from '../store/languageStore';
import { formatMoney } from '../lib/format';
import { getProductBasePrice } from '../lib/catalog';
import { getLocationParams } from '../lib/location';
import { cardToProduct, HOME_STORES_PER_PAGE, isAcceptingOrders, NOT_ACCEPTING_TEXT, searchCards, searchStores } from '../lib/marketplace';
import { useCardToCart, useFeedCards, useStorePages } from '../hooks/useMarketplace';
import { useTrackSearch } from '../hooks/useAnalytics';
import type { Category, ProductCardItem } from '../types';

const NO_CATEGORIES: Category[] = [];
const NO_CARDS: ProductCardItem[] = [];

// The home screen. Wave M2: the dishes are light cards (GET /catalog?view=cards: the "+" of a dish without options adds
// it at once, the others load their store's menu for the product sheet) and the stores come a page at a time (the
// carousel shows the nearest ones). The search looks at the feed's cards (dish, description, restaurant) and at every
// store serving the address: while it is in use the remaining store pages are loaded.
export default function Home() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];
  const location = useMemo(() => getLocationParams(activeAddress), [activeAddress]);

  const cartItems = useCartStore((state) => state.items);

  const [searchQuery, setSearchQuery] = useState('');
  const query = searchQuery.trim().toLowerCase();
  const searching = query !== '';

  const feed = useFeedCards(location);
  const storePages = useStorePages(location, HOME_STORES_PER_PAGE, searching);
  const stores = storePages.stores;

  // Each card's store says whether it takes orders now (`store.accepting_orders`, the store list's rule)
  const accepting = (card: ProductCardItem) => isAcceptingOrders(card.store);
  const cart = useCardToCart();

  // Favourite restaurants of this visit (not stored anywhere yet)
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);

  const toggleFavoriteStore = (uuid: string) => {
    hapticFeedback('light');
    setFavoriteStores(prev =>
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

  const categories = feed.data?.categories ?? NO_CATEGORIES;
  const cards = feed.data?.products ?? NO_CARDS;
  const promos = feed.data?.promos ?? [];
  const parentCategories = useMemo(() => categories.filter(c => !c.parent_id), [categories]);
  const searchedProducts = useMemo(() => searchCards(cards, searchQuery), [cards, searchQuery]);
  const shownStores = searching ? searchStores(stores, query) : stores;
  // Analytics (wave P): the count of dishes and stores a search found and its length, never what was typed
  useTrackSearch(searchQuery, searchedProducts.length + shownStores.length, !feed.isPending && !storePages.isPending && (!storePages.hasNextPage || storePages.isFetchNextPageError));
  // Wave P: a marketplace without any store yet (a fresh production; the customer signed in with `store: null`)
  const marketplaceEmpty = !searching && stores.length === 0 && cards.length === 0;
  // Both lists are narrowed to the selected address: with one set, an empty answer means no store serves it
  const nothingAtAddress = marketplaceEmpty && Object.keys(location).length > 0;

  // A later store page that failed (a search loading the rest) keeps what is there
  const error = feed.error ?? (storePages.data ? null : storePages.error);
  const loading = feed.isPending || storePages.isPending;

  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pb-4 pt-2">
        <PageHeader title={<BrandLogo />} />
        <InlineError
          onRetry={() => {
            if (feed.error) void feed.refetch();
            if (storePages.error) void storePages.refetch();
          }}
        />
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
  const featuredProduct = feed.data?.highlight_product ?? null;

  const displayAddress = activeAddress ? activeAddress.address : "Manzilni qo'shish";

  return (
    <div className="flex flex-col gap-5 px-4 pb-4 pt-2 animate-fade-in">
      {/* Brand and delivery address */}
      <header className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <h1 className="flex">
            <BrandLogo />
          </h1>
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
            <p className="text-body font-bold text-fg">{formatMoney(getProductBasePrice(cardToProduct(featuredProduct)))}</p>
            {accepting(featuredProduct) ? (
              <Button className="mt-1" onClick={() => void cart.start(featuredProduct)} disabled={cart.loadingId === featuredProduct.id}>
                Buyurtma qilish
              </Button>
            ) : (
              <p className="text-footnote font-medium text-fg-muted">{NOT_ACCEPTING_TEXT}</p>
            )}
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

      {nothingAtAddress && (
        <EmptyState
          icon={MapPin}
          title="Bu manzilga hozircha yetkazib beradigan oshxona yo'q"
          text="Boshqa manzilni tanlab ko'ring yoki keyinroq qayta kiring."
          action={
            <Button variant="secondary" onClick={() => navigate('/profile')}>
              Manzilni o'zgartirish
            </Button>
          }
        />
      )}

      {marketplaceEmpty && !nothingAtAddress && (
        <EmptyState
          icon={Store}
          title="Hozircha oshxonalar yo'q"
          text="Tez orada bu yerda oshxonalar va taomlar paydo bo'ladi. Keyinroq qayta kiring."
        />
      )}

      {/* Dishes of all restaurants */}
      {!marketplaceEmpty && <Section
        title={searching ? 'Taomlar' : 'Siz uchun tavsiyalar'}
        action={searching ? <span className="text-footnote text-fg-muted">{searchedProducts.length} ta natija</span> : undefined}
      >
        {searchedProducts.length === 0 && searchQuery ? (
          <EmptyState icon={Search} title="Hech narsa topilmadi" text="Boshqa so'z bilan qidirib ko'ring" />
        ) : (
          <div className="grid grid-cols-2 gap-3">
            {searchedProducts.map((card) => {
              const cartQty = cartItems
                .filter(item => item.productId === card.id)
                .reduce((sum, item) => sum + item.quantity, 0);

              return (
                <ProductCard
                  key={card.id}
                  product={cardToProduct(card)}
                  storeName={card.store?.name}
                  priceUnit={card.unit}
                  cartQty={cartQty}
                  onOpen={card.store?.uuid ? () => navigate(`/store/${card.store.uuid}`) : undefined}
                  onAdd={() => void cart.start(card)}
                  busy={cart.loadingId === card.id}
                  blockedText={accepting(card) ? undefined : NOT_ACCEPTING_TEXT}
                />
              );
            })}
          </div>
        )}
      </Section>}

      <ReplaceCartDialog pending={cart.pending} onClose={cart.clearPending} />

      {/* The product sheet of a dish with options: variant, modifiers, weight (from its store's menu) */}
      <ProductSheet
        isOpen={!!cart.sheetProduct}
        onClose={cart.closeSheet}
        product={cart.sheetProduct}
        availability={cart.availability}
        onAdd={cart.addFromSheet}
      />
    </div>
  );
}
