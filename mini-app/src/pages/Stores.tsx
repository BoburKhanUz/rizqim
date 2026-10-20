import { useState, useEffect, useMemo } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { Store } from 'lucide-react';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { getLocationParams } from '../lib/location';
import { EmptyState, InlineError, PageHeader } from '../components/ui';
import { SearchField } from '../components/catalog/SearchField';
import { StoreCard } from '../components/catalog/StoreCard';
import type { ApiResponse, StoreListItem } from '../types';

import { useAddressStore } from '../store/useAddressStore';

export default function Stores() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const [stores, setStores] = useState<StoreListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by "Qayta urinish" after a failed load
  const [searchQuery, setSearchQuery] = useState('');
  // Favourite restaurants of this visit (not stored anywhere yet)
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];

  useEffect(() => {
    let cancelled = false;
    const fetchStores = async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await api.get<ApiResponse<StoreListItem[]>>('/stores', { params: getLocationParams(activeAddress) });
        if (res.data.success && !cancelled) {
          setStores(res.data.data || []);
        }
      } catch (err) {
        logApiError('Failed to load stores:', err);
        if (!cancelled) setError(err);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchStores();
    return () => {
      cancelled = true;
    };
  }, [activeAddress, reloadKey]);

  const toggleFavoriteStore = (uuid: string) => {
    hapticFeedback('medium');
    setFavoriteStores(prev =>
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

  const filteredStores = useMemo(() => {
    if (!searchQuery) return stores;
    return stores.filter(store =>
      store.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (store.description && store.description.toLowerCase().includes(searchQuery.toLowerCase()))
    );
  }, [stores, searchQuery]);

  return (
    <div className="flex flex-col gap-4 px-4 pb-4 pt-2 animate-fade-in">
      <PageHeader
        back
        title={uz ? 'Barcha oshxonalar' : 'Все рестораны'}
        subtitle={loading || error ? undefined : `${filteredStores.length} ${uz ? 'oshxona' : 'ресторанов'}`}
      />

      <SearchField
        value={searchQuery}
        onChange={setSearchQuery}
        placeholder={uz ? 'Oshxonalarni qidirish...' : 'Поиск ресторанов...'}
      />

      {error ? (
        <InlineError onRetry={() => setReloadKey((key) => key + 1)} />
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
      ) : filteredStores.length === 0 ? (
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
        </div>
      )}
    </div>
  );
}
