import { Clock, Star } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { StoreListItem } from '../../types';
import { formatMoney } from '../../lib/format';
import { useLanguageStore } from '../../store/languageStore';
import { cx } from '../ui';
import { FavoriteButton } from './FavoriteButton';
import { StoreLogo } from './StoreLogo';

interface StoreCardProps {
  store: StoreListItem;
  isFavorite: boolean;
  onToggleFavorite: () => void;
  // compact: a tile of the home carousel; wide: a row of the list of all restaurants
  variant: 'compact' | 'wide';
}

// A restaurant of the lists. Only what the API really sent is shown: no rating, delivery time or minimum order is made up.
export function StoreCard({ store, isFavorite, onToggleFavorite, variant }: StoreCardProps) {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const rating = Number(store.rating);
  const hasRating = rating > 0 && store.review_count > 0;
  const wide = variant === 'wide';

  return (
    <article className={cx('relative flex flex-col rounded-card border border-line bg-surface', wide ? 'w-full' : 'w-60 shrink-0')}>
      <Link to={`/store/${store.uuid}`} className="flex flex-1 flex-col gap-3 rounded-card p-4 focus-visible:outline-2 focus-visible:outline-brand">
        <div className="flex items-center gap-3 pr-9">
          <StoreLogo name={store.name} logoUrl={store.logo_url} className={cx('rounded-card', wide ? 'size-16' : 'size-12')} />
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-body font-bold text-fg">{store.name}</h3>
            <span className="mt-0.5 flex flex-wrap items-center gap-x-2 text-caption text-fg-muted">
              {!store.is_open && (
                <span className="rounded-control bg-surface-2 px-1.5 py-0.5 font-bold text-fg-muted">{uz ? 'Yopiq' : 'Закрыто'}</span>
              )}
              {hasRating && (
                <span className="flex items-center gap-1 text-fg">
                  <Star aria-hidden="true" className="size-3.5 fill-accent text-accent" />
                  {rating.toFixed(1)}
                </span>
              )}
              {store.delivery_time && (
                <span className="flex items-center gap-1">
                  <Clock aria-hidden="true" className="size-3.5" />
                  {store.delivery_time}
                </span>
              )}
            </span>
          </div>
        </div>
        {store.description && <p className="line-clamp-2 text-footnote text-fg-muted">{store.description}</p>}
        {store.min_order > 0 && (
          <p className="mt-auto border-t border-line pt-2 text-caption text-fg-muted">
            {uz ? 'Minimal buyurtma' : 'Минимальный заказ'}: <span className="font-bold text-fg">{formatMoney(store.min_order)}</span>
          </p>
        )}
      </Link>
      <FavoriteButton active={isFavorite} onToggle={onToggleFavorite} className="absolute right-1 top-1" />
    </article>
  );
}
