import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useCartStore } from '../store/cartStore';
import { hapticFeedback } from '../lib/telegram';
import { isTelegramHost, selectionChanged } from '../lib/twa';
import { EmptyState, IconButton, InlineError, ProductImage, cx } from '../components/ui';
import api from '../services/api';
import { ArrowLeft, Clock, MessageSquare, Plus, Star } from 'lucide-react';
import ProductSheet from '../components/ProductSheet';
import { FavoriteButton } from '../components/catalog/FavoriteButton';
import { QuantityStepper } from '../components/catalog/QuantityStepper';
import { ReplaceCartDialog } from '../components/catalog/ReplaceCartDialog';
import { SoldOutBadge } from '../components/catalog/SoldOutBadge';
import { StoreLogo } from '../components/catalog/StoreLogo';
import { useAddToCart } from '../hooks/useAddToCart';
import { useMenuAvailability } from '../hooks/useMenuAvailability';
import { getVariantStock } from '../lib/stock';
import { availabilityView, isProductUnavailable } from '../lib/menuAvailability';
import { formatQuantity, isMeasuredUnit, unitLabel } from '../lib/quantity';
import { formatDate, formatMoney } from '../lib/format';
import { getProductBasePrice, getStoreInfo, needsProductSheet } from '../lib/catalog';
import type { ApiResponse, BranchAvailability, Category, Product, StoreCatalog, StoreCatalogStore } from '../types';

const CHIP = 'min-h-11 shrink-0 cursor-pointer whitespace-nowrap rounded-control px-4 text-body font-medium transition-colors';

export default function StoreDetail() {
  const { id: uuid } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { items: cartItems, removeItem, updateQuantity } = useCartStore();

  const [store, setStore] = useState<StoreCatalogStore | null>(null);
  const [catalogAvailability, setCatalogAvailability] = useState<BranchAvailability[] | undefined>(undefined);
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by "Qayta urinish" after a failed load

  const [selectedParentCategoryId, setSelectedParentCategoryId] = useState<number | null>(null);
  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState<number | null>(null);
  const [activeSegment, setActiveSegment] = useState<'menu' | 'reviews'>('menu');
  const [isFav, setIsFav] = useState(false); // Favourite of this visit (not stored anywhere yet)

  useEffect(() => {
    if (!uuid) return;
    let cancelled = false;
    const fetchStoreCatalog = async () => {
      try {
        setError(null);
        const res = await api.get<ApiResponse<StoreCatalog>>(`/catalog?store_id=${uuid}`);
        if (res.data.success && !cancelled) {
          setStore(res.data.data.store);
          setCategories(res.data.data.categories);
          setProducts(res.data.data.products);
          setCatalogAvailability(res.data.data.availability);
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
  }, [uuid, reloadKey]);

  // A promo banner links here with ?product_id=...: that product's selector opens once the menu has loaded,
  // until the customer closes it.
  const targetProductId = searchParams.get('product_id');
  const [dismissedTargetId, setDismissedTargetId] = useState<string | null>(null);
  const targetProduct = useMemo(() => {
    if (!targetProductId || targetProductId === dismissedTargetId) return null;
    return products.find(p => p.id.toString() === targetProductId) ?? null;
  }, [targetProductId, dismissedTargetId, products]);

  // What the store cannot offer right now: the first picture from GET /catalog/availability (the catalog's own copy
  // until it arrives), then kept current by events / polling
  const { branches: availabilityBranches } = useMenuAvailability(store?.id, catalogAvailability);
  const availability = useMemo(() => availabilityView(availabilityBranches), [availabilityBranches]);
  const cart = useAddToCart({ storeInfoOf: () => getStoreInfo(store), availability });
  const variantModalProduct = cart.sheetProduct ?? targetProduct;

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

  // "-" takes one unit off the product's first cart line (the line goes when it was the last unit)
  const handleDecrement = (line: { id: string; quantity: number }) => {
    hapticFeedback('light');
    if (line.quantity > 1) updateQuantity(line.id, line.quantity - 1);
    else removeItem(line.id);
  };

  const selectSegment = (segment: 'menu' | 'reviews') => {
    if (segment !== activeSegment) selectionChanged();
    setActiveSegment(segment);
  };

  if (error) {
    return (
      <div className="p-4">
        <InlineError
          onRetry={() => {
            setLoading(true);
            setReloadKey((key) => key + 1);
          }}
        />
      </div>
    );
  }
  if (loading || !store) {
    return (
      <div aria-busy="true">
        <div className="h-40 animate-pulse bg-line" />
        <div className="-mt-6 space-y-3 px-4">
          <div className="space-y-3 rounded-card border border-line bg-surface p-4">
            <div className="flex items-center gap-3">
              <div className="size-14 animate-pulse rounded-card bg-surface-2" />
              <div className="flex-1 space-y-2">
                <div className="h-5 w-1/2 animate-pulse rounded-control bg-surface-2" />
                <div className="h-3 w-1/3 animate-pulse rounded-control bg-surface-2" />
              </div>
            </div>
            <div className="h-3 w-3/4 animate-pulse rounded-control bg-surface-2" />
          </div>
          {[1, 2, 3].map((i) => (
            <div key={i} className="flex gap-3 rounded-card border border-line bg-surface p-3">
              <div className="flex-1 space-y-2">
                <div className="h-4 w-1/3 animate-pulse rounded-control bg-surface-2" />
                <div className="h-3 w-3/4 animate-pulse rounded-control bg-surface-2" />
                <div className="h-4 w-1/4 animate-pulse rounded-control bg-surface-2" />
              </div>
              <div className="size-20 animate-pulse rounded-control bg-surface-2" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  const rating = Number(store.rating);
  const hasRating = rating > 0 && store.review_count > 0;
  const minOrder = store.min_order > 0 ? store.min_order : null;
  const deliveryFee = store.delivery_fee > 0 ? store.delivery_fee : null;
  const reviewCount = store.ratings?.length ?? 0;

  return (
    <div className="animate-fade-in">
      {/* Cover with the back and favourite buttons */}
      <div className="relative h-40 w-full bg-brand-soft">
        {store.banner_url && <img src={store.banner_url} alt="" className="size-full object-cover" />}
        <div className="absolute inset-x-3 top-3 flex items-center justify-between">
          {!isTelegramHost() ? (
            <IconButton icon={ArrowLeft} label="Orqaga" variant="surface" onClick={() => navigate('/')} />
          ) : (
            <span />
          )}
          <FavoriteButton active={isFav} onToggle={toggleFavoriteStore} onPhoto />
        </div>
      </div>

      <div className="relative -mt-6 flex flex-col gap-4 px-4 pb-4">
        {/* The restaurant */}
        <section className="flex flex-col gap-3 rounded-card border border-line bg-surface p-4">
          <div className="flex items-center gap-3">
            <StoreLogo name={store.name} logoUrl={store.logo_url} className="size-14 rounded-card" />
            <div className="min-w-0 flex-1">
              <h1 className="text-title font-bold text-fg">{store.name}</h1>
              <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-footnote text-fg-muted">
                <span
                  className={cx(
                    'rounded-control px-2 py-0.5 text-caption font-bold',
                    store.is_open ? 'bg-success-soft text-success-soft-fg' : 'bg-surface-2 text-fg-muted',
                  )}
                >
                  {store.is_open ? 'Ochiq' : 'Yopiq'}
                </span>
                {hasRating && (
                  <span className="flex items-center gap-1 text-fg">
                    <Star aria-hidden="true" className="size-4 fill-accent text-accent" />
                    {rating.toFixed(1)}
                    <span className="text-fg-muted">({store.review_count})</span>
                  </span>
                )}
                {store.delivery_time && (
                  <span className="flex items-center gap-1">
                    <Clock aria-hidden="true" className="size-4" />
                    {store.delivery_time}
                  </span>
                )}
              </div>
            </div>
          </div>
          {store.description && <p className="text-footnote text-fg-muted">{store.description}</p>}
          {(minOrder !== null || deliveryFee !== null) && (
            <dl className="grid grid-cols-2 gap-2 border-t border-line pt-3">
              {minOrder !== null && (
                <div>
                  <dt className="text-caption text-fg-muted">Minimal buyurtma</dt>
                  <dd className="text-body font-bold text-fg">{formatMoney(minOrder)}</dd>
                </div>
              )}
              {deliveryFee !== null && (
                <div>
                  <dt className="text-caption text-fg-muted">Kuryer xizmati</dt>
                  <dd className="text-body font-bold text-fg">{formatMoney(deliveryFee)}</dd>
                </div>
              )}
            </dl>
          )}
        </section>

        {!store.is_open && (
          <p role="status" className="rounded-card bg-warning-soft px-4 py-3 text-footnote text-warning-soft-fg">
            Oshxona hozir yopiq: buyurtma qabul qilinmaydi.
          </p>
        )}

        {/* Menu / reviews */}
        <div role="tablist" aria-label="Oshxona bo'limlari" className="sticky top-0 z-10 -mx-4 flex gap-1 bg-page px-4 py-1">
          {([['menu', 'Menyu'], ['reviews', reviewCount > 0 ? `Fikrlar (${reviewCount})` : 'Fikrlar']] as const).map(([segment, label]) => (
            <button
              key={segment}
              type="button"
              role="tab"
              aria-selected={activeSegment === segment}
              onClick={() => selectSegment(segment)}
              className={cx(
                'min-h-11 flex-1 cursor-pointer rounded-control text-body font-bold transition-colors',
                activeSegment === segment ? 'bg-brand text-on-brand' : 'bg-surface text-fg-muted',
              )}
            >
              {label}
            </button>
          ))}
        </div>

        {activeSegment === 'menu' ? (
          <div className="flex flex-col gap-5">
            {/* Categories Selector */}
            {parentCategories.length > 0 && (
              <div className="flex flex-col gap-2">
                <div className="scrollbar-hide -mx-4 flex gap-2 overflow-x-auto px-4">
                  {parentCategories.map((cat) => {
                    const isSelected = selectedParentCategoryId === cat.id;
                    return (
                      <button
                        key={cat.id}
                        type="button"
                        aria-pressed={isSelected}
                        onClick={() => {
                          selectionChanged();
                          setSelectedParentCategoryId(isSelected ? null : cat.id);
                          setSelectedSubCategoryId(null);
                        }}
                        className={cx(CHIP, isSelected ? 'bg-brand text-on-brand' : 'border border-line bg-surface text-fg')}
                      >
                        {cat.name}
                      </button>
                    );
                  })}
                </div>

                {/* Subcategories (Second Row) */}
                {selectedParentCategoryId && subCategories.length > 0 && (
                  <div className="scrollbar-hide -mx-4 flex gap-2 overflow-x-auto px-4">
                    {subCategories.map((subCat) => {
                      const isSelected = selectedSubCategoryId === subCat.id;
                      return (
                        <button
                          key={subCat.id}
                          type="button"
                          aria-pressed={isSelected}
                          onClick={() => {
                            selectionChanged();
                            setSelectedSubCategoryId(isSelected ? null : subCat.id);
                          }}
                          className={cx(CHIP, isSelected ? 'bg-brand-soft text-brand-soft-fg' : 'border border-line bg-surface text-fg-muted')}
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
                <section key={catName} aria-label={catName} className="flex flex-col gap-2">
                  <h2 className="px-1 text-headline font-bold text-fg">{catName}</h2>
                  <ul className="flex flex-col gap-2">
                    {productsGroup.map((product) => {
                      const cartItemsArray = cartItems.filter(item => item.productId === product.id);
                      const cartQty = cartItemsArray.reduce((sum, item) => sum + item.quantity, 0);
                      const isSoldOut = isProductUnavailable(product, availability);
                      // A dish with variants, modifiers or a weight to choose opens its sheet; the others have a stepper
                      const needsSheet = needsProductSheet(product);
                      const measuredVariant = product.variants?.find((variant) => isMeasuredUnit(variant.unit));
                      // Single-variant products: the "+" stops at the available stock
                      const singleVariantStock = product.variants?.length === 1 ? getVariantStock(product.variants[0]) : null;
                      const isAtStockLimit = singleVariantStock !== null && cartQty >= singleVariantStock;
                      const inCartText = measuredVariant
                        ? formatQuantity(cartQty, measuredVariant.unit)
                        : String(cartItemsArray.reduce((sum, item) => sum + item.quantity, 0));

                      return (
                        <li key={product.id} className="flex gap-3 rounded-card border border-line bg-surface p-3">
                          <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <h3 className={cx('text-body font-bold', isSoldOut || !store.is_open ? 'text-fg-muted' : 'text-fg')}>{product.name}</h3>
                            {product.description && <p className="line-clamp-2 text-footnote text-fg-muted">{product.description}</p>}
                            <div className="mt-auto flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-1">
                              <span className={cx('text-body font-bold', isSoldOut ? 'text-fg-muted' : 'text-fg')}>
                                {formatMoney(getProductBasePrice(product))}
                                {measuredVariant && <span className="text-footnote font-medium text-fg-muted"> / {unitLabel(measuredVariant.unit)}</span>}
                              </span>
                              {isSoldOut ? (
                                <SoldOutBadge className="ml-auto" nextBatch={availability.nextBatch(product.id)} />
                              ) : (
                                store.is_open &&
                                (needsSheet ? (
                                  <div className="ml-auto flex items-center gap-2">
                                    {cartQty > 0 && <span className="text-footnote font-medium text-fg-muted">Savatda: {inCartText}</span>}
                                    <button
                                      type="button"
                                      aria-label={`Qo'shish: ${product.name}`}
                                      onClick={() => cart.start(product)}
                                      className="flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full bg-brand-soft px-4 text-body font-bold text-brand-soft-fg transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand motion-safe:active:scale-95"
                                    >
                                      <Plus aria-hidden="true" className="size-[18px]" />
                                      Qo'shish
                                    </button>
                                  </div>
                                ) : cartQty > 0 ? (
                                  <QuantityStepper
                                    quantity={cartQty}
                                    removeAtOne
                                    incrementDimmed={isAtStockLimit}
                                    decrementLabel={`${product.name}: kamaytirish`}
                                    incrementLabel={`${product.name}: ko'paytirish`}
                                    onDecrement={() => handleDecrement(cartItemsArray[0])}
                                    onIncrement={() => cart.start(product)}
                                    className="ml-auto"
                                  />
                                ) : (
                                  <button
                                    type="button"
                                    aria-label={`Qo'shish: ${product.name}`}
                                    onClick={() => cart.start(product)}
                                    className="ml-auto flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full bg-brand-soft px-4 text-body font-bold text-brand-soft-fg transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand motion-safe:active:scale-95"
                                  >
                                    <Plus aria-hidden="true" className="size-[18px]" />
                                    Qo'shish
                                  </button>
                                ))
                              )}
                            </div>
                          </div>
                          <ProductImage src={product.image_url} className={cx('size-20 rounded-control', (isSoldOut || !store.is_open) && 'opacity-50 grayscale')} />
                        </li>
                      );
                    })}
                  </ul>
                </section>
              );
            })}
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {store?.ratings && store.ratings.length > 0 ? (
              store.ratings.map((rate) => (
                <article key={rate.id} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-body font-bold text-fg">{rate.user?.name || "Mijoz"}</p>
                      <p className="text-caption text-fg-muted">{formatDate(rate.created_at)}</p>
                    </div>
                    <span className="flex shrink-0 items-center gap-1 rounded-control bg-surface-2 px-2 py-1 text-footnote font-bold text-fg">
                      <Star aria-hidden="true" className="size-4 fill-accent text-accent" />
                      {rate.rating}
                    </span>
                  </div>
                  {rate.comment && <p className="border-t border-line pt-2 text-footnote text-fg">{rate.comment}</p>}
                </article>
              ))
            ) : (
              <EmptyState icon={MessageSquare} title="Hozircha fikrlar yo'q" />
            )}
          </div>
        )}
      </div>

      <ReplaceCartDialog pending={cart.pending} onClose={cart.clearPending} />

      {/* The product sheet: variant, modifiers, weight */}
      <ProductSheet
        isOpen={!!variantModalProduct}
        onClose={() => {
          cart.closeSheet();
          setDismissedTargetId(targetProductId);
        }}
        product={variantModalProduct}
        availability={availability}
        onAdd={(choice) => {
          if (variantModalProduct) cart.addVariant(variantModalProduct, choice.variant, choice);
        }}
      />
    </div>
  );
}
