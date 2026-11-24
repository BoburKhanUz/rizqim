import { useEffect, useMemo, useRef, useState, type RefObject } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { Store } from 'lucide-react';
import { getLocationParams } from '../lib/location';
import { searchStores, STORES_PER_PAGE } from '../lib/marketplace';
import { Button, EmptyState, InlineError, PageHeader } from '../components/ui';
import { SearchField } from '../components/catalog/SearchField';
import { StoreCard } from '../components/catalog/StoreCard';
import { useStorePages } from '../hooks/useMarketplace';
import { useTrackSearch } from '../hooks/useAnalytics';

import { useAddressStore } from '../store/useAddressStore';

// Loads the next page when `target` comes near the screen (infinite scroll). Without IntersectionObserver nothing
// happens here and the "more" button below the list does it.
function useNearScreen(target: RefObject<Element | null>, onNear: () => void, active: boolean) {
  const callback = useRef(onNear);
  useEffect(() => {
    callback.current = onNear;
  });
  useEffect(() => {
    const element = target.current;
    if (!active || !element || typeof IntersectionObserver === 'undefined') return;
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) callback.current();
    }, { rootMargin: '400px 0px' });
    observer.observe(element);
    return () => observer.disconnect();
  }, [target, active]);
}

// Every restaurant serving the address, nearest first. Wave M2: 20 at a time (GET /stores?page=&per_page=), the next
// page as the list is scrolled; a search loads the remaining pages, so it still looks at every store.
export default function Stores() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const [searchQuery, setSearchQuery] = useState('');
  // Favourite restaurants of this visit (not stored anywhere yet)
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];
  const location = useMemo(() => getLocationParams(activeAddress), [activeAddress]);
  const searching = searchQuery.trim() !== '';

  const pages = useStorePages(location, STORES_PER_PAGE, searching);
  const { stores, total, hasNextPage, isFetchingNextPage, isFetchNextPageError, fetchNextPage } = pages;
  const loading = pages.isPending;
  // A later page that failed keeps the pages already there (its own retry is under the list)
  const error = pages.isError && !pages.data ? pages.error : null;

  const sentinel = useRef<HTMLDivElement | null>(null);
  useNearScreen(sentinel, () => {
    if (hasNextPage && !isFetchingNextPage && !isFetchNextPageError) void fetchNextPage();
  }, !loading && !error && hasNextPage === true);

  const toggleFavoriteStore = (uuid: string) => {
    hapticFeedback('medium');
    setFavoriteStores(prev =>
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

  const filteredStores = useMemo(() => searchStores(stores, searchQuery, true), [stores, searchQuery]);
  // The count of the header: every store serving the address (the server says how many), or the matches of a search
  const count = searching ? filteredStores.length : (total ?? filteredStores.length);
  // Analytics (wave P): how many stores a search found and how long it was, never what was typed. Once every page is in
  useTrackSearch(searchQuery, filteredStores.length, !loading && (!hasNextPage || isFetchNextPageError));

  return (
    <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
      <PageHeader
        back
        title={uz ? 'Barcha oshxonalar' : 'Все рестораны'}
        subtitle={loading || error ? undefined : `${count} ${uz ? 'oshxona' : 'ресторанов'}`}
      />

      <SearchField
        value={searchQuery}
        onChange={setSearchQuery}
        placeholder={uz ? 'Oshxonalarni qidirish' : 'Поиск ресторанов'}
      />

      {error ? (
        <InlineError onRetry={() => void pages.refetch()} />
      ) : loading ? (
        <div className="flex flex-col gap-3" aria-busy="true">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="flex gap-3 rounded-card border border-line bg-surface p-4">
              <div className="size-16 animate-pulse rounded-card bg-surface-2" />
              <div className="flex-1 space-y-2 pt-1">
                <div className="h-4 w-2/3 animate-pulse rounded-control bg-surface-2" />
                <div className="h-3 w-full animate-pulse rounded-control bg-surface-2" />
              </div>
            </div>
          ))}
        </div>
      ) : filteredStores.length === 0 && !hasNextPage ? (
        <EmptyState icon={Store} title={uz ? 'Oshxonalar topilmadi' : 'Рестораны не найдены'} />
      ) : (
        <div className="flex flex-col gap-3 animate-fade-in">
          {filteredStores.map((store) => (
            <StoreCard
              key={store.id}
              store={store}
              variant="wide"
              isFavorite={favoriteStores.includes(store.uuid)}
              onToggleFavorite={() => toggleFavoriteStore(store.uuid)}
            />
          ))}
          {hasNextPage && (
            <div ref={sentinel} className="flex flex-col items-center gap-2 py-2">
              {isFetchNextPageError ? (
                <InlineError onRetry={() => void fetchNextPage()} />
              ) : (
                <Button variant="secondary" onClick={() => void fetchNextPage()} disabled={isFetchingNextPage} aria-busy={isFetchingNextPage || undefined}>
                  {isFetchingNextPage ? (uz ? 'Yuklanmoqda...' : 'Загрузка...') : uz ? "Ko'proq ko'rsatish" : 'Показать ещё'}
                </Button>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
