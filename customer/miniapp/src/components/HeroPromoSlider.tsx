import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { useVisibleInterval } from '../hooks/useVisibleInterval';
import { useLanguageStore } from '../store/languageStore';
import { cx } from './ui';
import type { Promo } from '../types';

const SLIDE_INTERVAL_MS = 5000;

export default function HeroPromoSlider({ promos = [] }: { promos?: Promo[] }) {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const [activeIndex, setActiveIndex] = useState(0);
  const [touchStart, setTouchStart] = useState<number | null>(null);
  const [touchEnd, setTouchEnd] = useState<number | null>(null);

  const minSwipeDistance = 50;

  const onTouchStart = (e: React.TouchEvent) => {
    setTouchEnd(null);
    setTouchStart(e.targetTouches[0].clientX);
  };

  const onTouchMove = (e: React.TouchEvent) => {
    setTouchEnd(e.targetTouches[0].clientX);
  };

  const onTouchEnd = () => {
    if (!touchStart || !touchEnd) return;
    const distance = touchStart - touchEnd;
    const isLeftSwipe = distance > minSwipeDistance;
    const isRightSwipe = distance < -minSwipeDistance;

    if (isLeftSwipe) {
      hapticFeedback('light');
      setActiveIndex((prev) => (prev + 1) % promos.length);
    } else if (isRightSwipe) {
      hapticFeedback('light');
      setActiveIndex((prev) => (prev - 1 + promos.length) % promos.length);
    }
  };

  // Auto-advance only when there is something to advance to, and not while the page is hidden
  useVisibleInterval(
    () => setActiveIndex((prev) => (prev + 1) % promos.length),
    promos.length > 1 ? SLIDE_INTERVAL_MS : null,
  );

  if (!promos || promos.length === 0) return null;

  const uz = language === 'uz';
  const openPromo = (banner: Promo) => {
    hapticFeedback('medium');
    if (banner.product_id) {
      navigate(`/store/${banner.store_uuid}?product_id=${banner.product_id}`);
    } else {
      navigate(`/store/${banner.store_uuid}`);
    }
  };

  return (
    <section
      aria-roledescription="carousel"
      aria-label={uz ? 'Aksiyalar' : 'Акции'}
      className="flex flex-col"
    >
      <div
        onTouchStart={onTouchStart}
        onTouchMove={onTouchMove}
        onTouchEnd={onTouchEnd}
        className="relative h-44 overflow-hidden rounded-card bg-brand-soft"
      >
        <div
          className="flex h-full transition-transform duration-700 ease-out motion-reduce:transition-none"
          style={{ transform: `translateX(-${activeIndex * 100}%)` }}
        >
          {promos.map((banner, index) => {
            const hasImage = !!banner.image_url;
            const isClickable = !!banner.store_uuid;
            // A photo gets a dark overlay and white text; a banner without a photo is the solid brand colour
            const text = hasImage ? 'text-white' : 'text-on-brand'; // design-ok: text over a photo with a dark overlay
            const content = (
              <>
                {hasImage ? (
                  <>
                    <img src={banner.image_url ?? undefined} alt="" className="absolute inset-0 size-full object-cover" />
                    <span className="absolute inset-0 bg-scrim" />
                    <span className="absolute inset-0 bg-linear-to-t from-scrim via-scrim/60 to-transparent" />
                  </>
                ) : (
                  <span className="absolute inset-0 bg-brand" />
                )}
                <span className={cx('absolute inset-x-0 bottom-0 flex flex-col items-start gap-1.5 p-4', text)}>
                  {isClickable && (
                    <span className="rounded-control bg-surface px-2 py-0.5 text-caption font-bold text-fg">{uz ? 'Aksiya' : 'Акция'}</span>
                  )}
                  <span className="line-clamp-2 text-left text-headline font-bold">{banner.title}</span>
                </span>
              </>
            );
            const slideClass = 'relative block h-full w-full shrink-0 overflow-hidden';
            const slideLabel = `${index + 1} / ${promos.length}`;
            return isClickable ? (
              <button
                key={banner.id}
                type="button"
                role="group"
                aria-roledescription="slide"
                aria-label={slideLabel}
                aria-hidden={index !== activeIndex || undefined}
                tabIndex={index === activeIndex ? 0 : -1}
                onClick={() => openPromo(banner)}
                className={cx(slideClass, 'cursor-pointer')}
              >
                {content}
              </button>
            ) : (
              <div
                key={banner.id}
                role="group"
                aria-roledescription="slide"
                aria-label={slideLabel}
                aria-hidden={index !== activeIndex || undefined}
                className={slideClass}
              >
                {content}
              </div>
            );
          })}
        </div>
      </div>

      {promos.length > 1 && (
        <div className="flex justify-center">
          {promos.map((_, idx) => (
            <button
              key={idx}
              type="button"
              aria-label={`${uz ? 'Aksiya' : 'Акция'} ${idx + 1}`}
              aria-current={activeIndex === idx || undefined}
              onClick={() => {
                selectionChanged();
                setActiveIndex(idx);
              }}
              className="flex size-11 cursor-pointer items-center justify-center"
            >
              <span className={cx('h-2 rounded-full transition-all duration-300', activeIndex === idx ? 'w-6 bg-brand' : 'w-2 bg-fg-subtle')} />
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
