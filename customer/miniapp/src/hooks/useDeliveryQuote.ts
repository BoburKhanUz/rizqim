import { useQuery } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import api from '../services/api';
import { useDebouncedValue } from './useDebouncedValue';
import { isDeliveryRefusalReason, refusalMessage, refusalReasonOf } from '../lib/orderErrors';
import type { LatLng } from '../features/address/types';
import type { ApiErrorBody, ApiResponse, DeliveryQuote, DeliveryRefusalReason } from '../types';

// What the server says delivery to the chosen address costs (GET /delivery/quote, docs/ZONES.md). The app does no fee
// arithmetic of its own: the zone's price (free past its threshold), the minimum order, the time and the branch all come
// from here, and the order is priced by the same quoter when it is placed.
export type DeliveryQuoteState =
  // Nothing to ask: pickup, or no address with a pin yet
  | { status: 'off' }
  // Asked, or the cart changed a moment ago and is about to be asked again
  | { status: 'loading' }
  | { status: 'ready'; quote: DeliveryQuote }
  // The server will not deliver there ("bu manzilga yetkazib berilmaydi"), with the sentence to show
  | { status: 'refused'; reason: DeliveryRefusalReason; message: string }
  // The store has drawn no zones yet: it keeps its own delivery fee and the radius rule of its branches
  | { status: 'unconfigured' }
  // The quote could not be asked for (offline, a server error, too many requests)
  | { status: 'error' };

type QuoteResult =
  | { kind: 'ready'; quote: DeliveryQuote }
  | { kind: 'refused'; reason: DeliveryRefusalReason; message: string }
  | { kind: 'unconfigured' };

// A change of the cart is asked after it settled: tapping "+" five times is one request, not five (the quote is rate limited).
const SETTLE_MS = 400;

export async function fetchDeliveryQuote(store: string, point: LatLng, subtotal: number): Promise<QuoteResult> {
  try {
    const { data } = await api.get<ApiResponse<DeliveryQuote>>('/delivery/quote', {
      params: { store, lat: point.lat, lng: point.lng, subtotal },
    });
    return { kind: 'ready', quote: data.data };
  } catch (error) {
    const response = isAxiosError<ApiErrorBody>(error) ? error.response : undefined;
    const reason = response?.status === 422 ? refusalReasonOf(response.data) : undefined;
    if (reason === 'zones_not_configured') return { kind: 'unconfigured' };
    if (isDeliveryRefusalReason(reason)) {
      return { kind: 'refused', reason, message: refusalMessage(reason, null) ?? response?.data?.message ?? "Bu manzilga yetkazib berilmaydi." };
    }
    throw error;
  }
}

interface Options {
  storeId: string | undefined;
  // The pin of the chosen address; null until the address has one
  point: LatLng | null;
  // The cart total before delivery and discount, in whole so'm
  subtotal: number;
  enabled: boolean;
}

export function useDeliveryQuote({ storeId, point, subtotal, enabled }: Options): DeliveryQuoteState & { retry: () => void } {
  const settled = useDebouncedValue(subtotal, SETTLE_MS);
  const active = enabled && storeId !== undefined && point !== null && subtotal > 0;

  const query = useQuery({
    queryKey: ['delivery-quote', storeId, point?.lat, point?.lng, settled],
    queryFn: () => fetchDeliveryQuote(storeId as string, point as LatLng, settled),
    enabled: active,
    staleTime: 30_000,
    // A refusal is an answer, not a failure; only a request that did not get one is retried (once)
    retry: (count, error) => count < 1 && !(isAxiosError(error) && error.response !== undefined && error.response.status < 500),
    retryDelay: 1500,
  });

  const retry = () => void query.refetch();

  if (!active) return { status: 'off', retry };
  if (settled !== subtotal || query.isPending) return query.isError ? { status: 'error', retry } : { status: 'loading', retry };
  if (query.isError) return { status: 'error', retry };

  const result = query.data;
  if (result?.kind === 'ready') return { status: 'ready', quote: result.quote, retry };
  if (result?.kind === 'refused') return { status: 'refused', reason: result.reason, message: result.message, retry };
  if (result?.kind === 'unconfigured') return { status: 'unconfigured', retry };
  return { status: 'loading', retry };
}
