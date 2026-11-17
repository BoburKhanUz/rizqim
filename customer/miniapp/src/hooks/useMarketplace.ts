import { useEffect, useRef, useState } from 'react';
import { useInfiniteQuery, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { showAlert } from '../lib/dialogs';
import { hapticFeedback } from '../lib/telegram';
import { getStoreInfo } from '../lib/catalog';
import { availabilityView, NO_AVAILABILITY, type AvailabilityView } from '../lib/menuAvailability';
import { addsDirectly, cardToProduct, FEED_CARD_LIMIT, isAcceptingOrders, nextStorePage, NOT_ACCEPTING_TEXT, storesOfPages, type StorePageMeta } from '../lib/marketplace';
import type { LocationParams } from '../lib/location';
import type { CartStoreInfo } from '../store/cartStore';
import type { ApiResponse, FeedCards, ProductCardItem, StoreCatalog, StoreListItem } from '../types';
import type { ProductChoice } from '../components/ProductSheet';
import { useAddToCart } from './useAddToCart';

// Wave M2: the marketplace reads of the customer screens (docs/API.md "Market o'qish modeli"). The browser's HTTP cache
// revalidates them (ETag, `Cache-Control: private, no-cache`): a repeated read of an unchanged feed is a 304.

// --- The feed: light cards ------------------------------------------------------------------------------------------

export const feedKey = (location: LocationParams, categoryId?: number | string) => ['market', 'feed', location, categoryId ?? null] as const;

export async function fetchFeedCards(location: LocationParams, categoryId?: number | string): Promise<FeedCards> {
  const params: Record<string, string | number> = { ...location, view: 'cards', limit: FEED_CARD_LIMIT };
  if (categoryId !== undefined) params.category_id = categoryId;
  const { data } = await api.get<ApiResponse<FeedCards>>('/catalog', { params });
  return {
    categories: data.data?.categories ?? [],
    products: data.data?.products ?? [],
    promos: data.data?.promos ?? [],
    highlight_product: data.data?.highlight_product ?? null,
  };
}

// The home feed (or a category's): read when the screen opens and when the address changes. Not polled.
export function useFeedCards(location: LocationParams, categoryId?: number | string, enabled = true) {
  return useQuery({
    queryKey: feedKey(location, categoryId),
    queryFn: () => fetchFeedCards(location, categoryId),
    enabled,
    staleTime: 30_000,
    retry: 1,
  });
}

// --- The store lists: pages --------------------------------------------------------------------------------------

export interface StoresPage {
  stores: StoreListItem[];
  meta: StorePageMeta | undefined;
}

export async function fetchStoresPage(location: LocationParams, page: number, perPage: number): Promise<StoresPage> {
  const { data } = await api.get<ApiResponse<StoreListItem[], StorePageMeta>>('/stores', { params: { ...location, page, per_page: perPage } });
  return { stores: data.data ?? [], meta: data.meta };
}

// The stores serving the address, nearest first, `perPage` at a time (fetchNextPage loads the next one). `loadAll`
// keeps loading until the last page: a search over the stores must see every one of them, as before the paging.
export function useStorePages(location: LocationParams, perPage: number, loadAll = false) {
  const query = useInfiniteQuery({
    queryKey: ['market', 'stores', location, perPage] as const,
    queryFn: ({ pageParam }) => fetchStoresPage(location, pageParam, perPage),
    initialPageParam: 1,
    getNextPageParam: (last) => nextStorePage(last.meta),
    staleTime: 60_000,
    retry: 1,
  });
  const { hasNextPage, fetchNextPage } = query;
  useEffect(() => {
    if (!loadAll || !hasNextPage) return;
    let stopped = false;
    // One page after the other until the last (or a failure: the list then offers its retry)
    const loadRest = async () => {
      let result = await fetchNextPage();
      while (!stopped && result.hasNextPage && !result.isFetchNextPageError) result = await result.fetchNextPage();
    };
    void loadRest();
    return () => {
      stopped = true;
    };
  }, [loadAll, hasNextPage, fetchNextPage]);

  return { ...query, stores: storesOfPages(query.data?.pages), total: query.data?.pages[0]?.meta?.total };
}

// --- A card into the cart ---------------------------------------------------------------------------------------------

export const storeMenuKey = (storeUuid: string) => ['market', 'store-menu', storeUuid] as const;

export async function fetchStoreMenu(storeUuid: string): Promise<StoreCatalog> {
  const { data } = await api.get<ApiResponse<StoreCatalog>>(`/catalog?store_id=${storeUuid}`);
  return data.data;
}

/**
 * The "+" of a card, for the customer exactly what the "+" of a full product was:
 *  - a dish with one plain variant (needs_options false) goes in at once;
 *  - anything else (variants, modifiers, a weight, a combo) loads the store's menu (GET /catalog?store_id=, kept 30 s)
 *    and opens the product sheet of the full product, with what the menu cannot offer now greyed out;
 *  - a store that does not take orders now is refused with "Hozir buyurtma qabul qilmayapti";
 *  - food of another restaurant in the cart asks first (`pending`), stock limits are the same as before.
 */
export function useCardToCart() {
  const queryClient = useQueryClient();
  const storeOf = useRef(new Map<number, CartStoreInfo | undefined>());
  const [availability, setAvailability] = useState<AvailabilityView>(NO_AVAILABILITY);
  const [loadingId, setLoadingId] = useState<number | null>(null);
  const cart = useAddToCart({ storeInfoOf: (product) => storeOf.current.get(product.id), availability });

  const refuse = (message: string) => {
    hapticFeedback('error');
    void showAlert(message);
  };

  const start = async (card: ProductCardItem) => {
    if (!isAcceptingOrders(card.store)) {
      refuse(`"${card.store.name}": ${NOT_ACCEPTING_TEXT.toLowerCase()}.`);
      return;
    }
    storeOf.current.set(card.id, getStoreInfo(card.store));
    if (addsDirectly(card)) {
      setAvailability(NO_AVAILABILITY);
      cart.start(cardToProduct(card));
      return;
    }
    if (loadingId !== null) return;
    setLoadingId(card.id);
    try {
      const menu = await queryClient.fetchQuery({ queryKey: storeMenuKey(card.store.uuid), queryFn: () => fetchStoreMenu(card.store.uuid), staleTime: 30_000 });
      if (!isAcceptingOrders(menu.store)) {
        refuse(`"${card.store.name}": ${NOT_ACCEPTING_TEXT.toLowerCase()}.`);
        return;
      }
      const product = menu.products.find((candidate) => candidate.id === card.id);
      if (!product) {
        refuse(`Uzr, "${card.name}" hozir mavjud emas.`);
        return;
      }
      storeOf.current.set(product.id, getStoreInfo(menu.store) ?? getStoreInfo(card.store));
      setAvailability(availabilityView(menu.availability));
      cart.start(product);
    } catch {
      refuse("Taomni ochib bo'lmadi. Internetni tekshirib, qayta urinib ko'ring.");
    } finally {
      setLoadingId(null);
    }
  };

  return {
    start,
    loadingId,
    sheetProduct: cart.sheetProduct,
    availability,
    closeSheet: cart.closeSheet,
    addFromSheet: (choice: ProductChoice) => {
      if (cart.sheetProduct) cart.addVariant(cart.sheetProduct, choice.variant, choice);
    },
    pending: cart.pending,
    clearPending: cart.clearPending,
  };
}
