import { useEffect, useRef, type ReactNode } from 'react';
import { Info } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { Button } from './ui/Button';

// Pieces shared by the screens that list orders in the two scopes of GET /orders (open ones, finished ones by page).
// The customer's and the staff's screens look the same: the tokens follow the theme, so `variant` is kept only so
// that callers need not change.

type Variant = 'customer' | 'staff';

const pick = (language: string, text: { uz: string; ru: string; en: string }) =>
  language === 'ru' ? text.ru : language === 'en' ? text.en : text.uz;

function Notice({ children, variant }: { children: ReactNode; variant?: Variant }) {
  return (
    <div role="status" data-variant={variant} className="flex items-start gap-2 rounded-card bg-warning-soft px-4 py-3 text-footnote text-warning-soft-fg">
      <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      <p>{children}</p>
    </div>
  );
}

// More open orders than the server sends in one answer (200): only the newest are on screen.
export function TruncatedOrdersNotice({ variant }: { variant?: Variant }) {
  const language = useLanguageStore((state) => state.language);
  return (
    <Notice variant={variant}>
      {pick(language, {
        uz: "Faol buyurtmalar juda ko'p: faqat eng yangi 200 tasi ko'rsatilmoqda.",
        ru: 'Активных заказов очень много: показаны только 200 самых новых.',
        en: 'There are a lot of open orders: only the newest 200 are shown.',
      })}
    </Notice>
  );
}

// A report built from fewer finished orders than its period holds (the history is loaded page by page, up to a limit).
export function PartialPeriodNotice() {
  const language = useLanguageStore((state) => state.language);
  return (
    <Notice>
      {pick(language, {
        uz: "Tanlangan davr uchun buyurtmalar to'liq yuklanmadi: ko'rsatkichlar eng yangi buyurtmalar bo'yicha hisoblangan.",
        ru: 'Заказы за выбранный период загружены не полностью: показатели посчитаны по самым новым заказам.',
        en: 'Orders for the selected period were not fully loaded: the figures cover the newest orders only.',
      })}
    </Notice>
  );
}

interface LoadMoreProps {
  hasNextPage: boolean;
  isFetchingNextPage: boolean;
  isFetching?: boolean; // the loaded pages are being refetched: the next one waits for that
  isError: boolean; // loading the next page failed: no automatic retry, the button retries
  loadedCount: number; // changes whenever a page arrives, so the sentinel is watched again
  onLoadMore: () => void;
  variant?: Variant; // unused: both audiences look the same
}

// The end of a paginated list: "Ko'proq yuklash" loads the next page, and so does scrolling it into view.
export function LoadMoreOrders({ hasNextPage, isFetchingNextPage, isFetching = false, isError, loadedCount, onLoadMore }: LoadMoreProps) {
  const language = useLanguageStore((state) => state.language);
  const sentinel = useRef<HTMLDivElement>(null);
  const latestLoadMore = useRef(onLoadMore);

  useEffect(() => {
    latestLoadMore.current = onLoadMore;
  });

  const canAutoLoad = hasNextPage && !isFetching && !isFetchingNextPage && !isError;
  useEffect(() => {
    const element = sentinel.current;
    if (!canAutoLoad || !element || typeof IntersectionObserver === 'undefined') return;
    // A fresh observer reports the sentinel's current state, so a page that is still short of the screen's end loads the next
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) latestLoadMore.current();
      },
      { rootMargin: '200px' },
    );
    observer.observe(element);
    return () => observer.disconnect();
  }, [canAutoLoad, loadedCount]);

  if (!hasNextPage) return null;

  return (
    <div ref={sentinel} className="flex flex-col items-center gap-2 py-2">
      {isError && (
        <p role="alert" className="text-footnote font-medium text-danger-soft-fg">
          {pick(language, {
            uz: "Keyingi buyurtmalarni yuklab bo'lmadi.",
            ru: 'Не удалось загрузить следующие заказы.',
            en: 'Could not load more orders.',
          })}
        </p>
      )}
      <Button variant="secondary" onClick={onLoadMore} disabled={isFetching || isFetchingNextPage} loading={isFetchingNextPage}>
        {isFetchingNextPage
          ? pick(language, { uz: 'Yuklanmoqda...', ru: 'Загрузка...', en: 'Loading...' })
          : pick(language, { uz: "Ko'proq yuklash", ru: 'Загрузить ещё', en: 'Load more' })}
      </Button>
    </div>
  );
}
