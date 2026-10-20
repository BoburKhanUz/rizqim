import { Plus } from 'lucide-react';
import type { Product } from '../../types';
import { formatMoney } from '../../lib/format';
import { getProductBasePrice } from '../../lib/catalog';
import { useLanguageStore } from '../../store/languageStore';
import { ProductImage } from '../ui';

interface ProductCardProps {
  product: Product;
  // How many of it are already in the cart (all its variants together)
  cartQty: number;
  // Open the restaurant of the dish; without it the card is not a link
  onOpen?: () => void;
  onAdd: () => void;
}

// A dish of the feed and of the category pages (two columns): photo, restaurant, name, description, price, add.
export function ProductCard({ product, cartQty, onOpen, onAdd }: ProductCardProps) {
  const { language } = useLanguageStore();
  const store = product.store;
  const name = <span className="line-clamp-2 text-left">{product.name}</span>;

  return (
    <article className="relative flex flex-col overflow-hidden rounded-card border border-line bg-surface">
      <div className="flex flex-col gap-1.5 p-3 pb-1">
        <div className="relative aspect-[4/3] w-full overflow-hidden rounded-control">
          <ProductImage src={product.image_url} className="size-full" />
          {cartQty > 0 && (
            <span className="absolute right-2 top-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-brand px-1.5 text-caption font-bold text-on-brand">
              {cartQty}
            </span>
          )}
        </div>
        {store && <p className="truncate text-caption font-medium text-fg-muted">{store.name}</p>}
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
        <span className="text-body font-bold text-fg">{formatMoney(getProductBasePrice(product))}</span>
        <button
          type="button"
          aria-label={language === 'uz' ? "Savatga qo'shish" : 'Добавить в корзину'}
          title={language === 'uz' ? "Savatga qo'shish" : 'Добавить в корзину'}
          onClick={onAdd}
          className="relative z-10 ml-auto flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full bg-brand-soft text-brand-soft-fg transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand motion-safe:active:scale-90"
        >
          <Plus aria-hidden="true" className="size-5" />
        </button>
      </div>
    </article>
  );
}
