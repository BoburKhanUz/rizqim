import { Plus } from 'lucide-react';
import type { MenuUnit, Product } from '../../types';
import { formatMoney } from '../../lib/format';
import { getProductBasePrice } from '../../lib/catalog';
import { isProductSoldOut } from '../../lib/stock';
import { isMeasuredUnit, unitLabel } from '../../lib/quantity';
import { useLanguageStore } from '../../store/languageStore';
import { ProductImage, cx } from '../ui';
import { SoldOutBadge } from './SoldOutBadge';

interface ProductCardProps {
  product: Product;
  // How many of it are already in the cart (all its variants together)
  cartQty: number;
  // Open the restaurant of the dish; without it the card is not a link
  onOpen?: () => void;
  onAdd: () => void;
  // The unit the price is per, when the product carries no variant to say it (a feed card with options)
  priceUnit?: MenuUnit | null;
  // The restaurant's name when the product does not carry its store (a feed card)
  storeName?: string;
  // The restaurant does not take orders now: this text instead of the add button
  blockedText?: string;
  // The full product is being loaded for its sheet (the add button waits)
  busy?: boolean;
}

// A dish of the feed and of the category pages (two columns): photo, restaurant, name, description, price, add.
export function ProductCard({ product, priceUnit, storeName, cartQty, onOpen, onAdd, blockedText, busy = false }: ProductCardProps) {
  const { language } = useLanguageStore();
  const store = product.store;
  // Only the stock the feed carries is known here (the stop-list is per store, see the store page)
  const soldOut = isProductSoldOut(product);
  const measured = (product.variants ?? []).find((variant) => isMeasuredUnit(variant.unit)) ?? (isMeasuredUnit(priceUnit ?? undefined) ? { unit: priceUnit ?? undefined } : undefined);
  const name = <span className="line-clamp-2 text-left">{product.name}</span>;

  return (
    <article className="relative flex flex-col overflow-hidden rounded-card border border-line bg-surface">
      <div className="flex flex-col gap-1.5 p-3 pb-1">
        <div className="relative aspect-[4/3] w-full overflow-hidden rounded-control">
          <ProductImage src={product.image_url} className={cx('size-full', soldOut && 'opacity-50 grayscale')} />
          {soldOut && <SoldOutBadge className="absolute left-2 top-2" />}
          {cartQty > 0 && (
            <span className="absolute right-2 top-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-brand px-1.5 text-caption font-bold text-on-brand">
              {cartQty}
            </span>
          )}
        </div>
        {(store?.name ?? storeName) && <p className="truncate text-caption font-medium text-fg-muted">{store?.name ?? storeName}</p>}
        <h3 className="text-body font-bold text-fg">
          {onOpen ? (
            // The whole card opens the restaurant (the add button stays above this overlay)
            <button type="button" onClick={onOpen} className="cursor-pointer text-left after:absolute after:inset-0 after:content-['']">
              {name}
            </button>
          ) : (
            name
          )}
        </h3>
        {product.description && <p className="line-clamp-2 text-footnote text-fg-muted">{product.description}</p>}
      </div>
      <div className="mt-auto flex flex-wrap items-center justify-between gap-x-2 gap-y-1 px-3 pb-2">
        <span className={cx('text-body font-bold', soldOut ? 'text-fg-muted' : 'text-fg')}>
          {formatMoney(getProductBasePrice(product))}
          {measured && <span className="text-footnote font-medium text-fg-muted"> / {unitLabel(measured.unit)}</span>}
        </span>
        {!soldOut && blockedText && <span className="text-caption font-medium text-fg-muted">{blockedText}</span>}
        {!soldOut && !blockedText && (
          <button
            type="button"
            aria-label={language === 'uz' ? "Savatga qo'shish" : 'Добавить в корзину'}
            title={language === 'uz' ? "Savatga qo'shish" : 'Добавить в корзину'}
            onClick={onAdd}
            disabled={busy}
            aria-busy={busy || undefined}
            className="relative z-10 ml-auto flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-brand-soft text-brand-soft-fg transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-60 motion-safe:active:scale-90"
          >
            <Plus aria-hidden="true" className={cx('size-5', busy && 'motion-safe:animate-pulse')} />
          </button>
        )}
      </div>
    </article>
  );
}
