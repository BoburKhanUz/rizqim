import type { Product, ProductCardItem, StoreListItem } from '../types';

// Wave M2: the home feed and the category pages show light cards (GET /catalog?view=cards) instead of full products,
// and the store lists come a page at a time (GET /stores?page=&per_page=). docs/API.md, "Market o'qish modeli".

// How many cards the feed asks for: the most the server gives (50), as many products as the full feed had, so the
// search over the feed finds what it found before.
export const FEED_CARD_LIMIT = 50;
// The home carousel shows the nearest stores; the list of all stores loads 20 at a time as it is scrolled.
export const HOME_STORES_PER_PAGE = 10;
export const STORES_PER_PAGE = 20;

export const NOT_ACCEPTING_TEXT = 'Hozir buyurtma qabul qilmayapti';

export interface StorePageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

// A card as the product the cart and the product card work with. Only a card without options (one plain variant) can go
// into the cart from this: its one variant is all the cart needs (id, uuid, price, unit, stock).
export function cardToProduct(card: ProductCardItem): Product {
  const variant = card.variant;
  return {
    id: card.id,
    uuid: card.uuid,
    store_id: card.store_id,
    category_id: card.category_id,
    name: card.name,
    slug: '',
    description: card.description,
    image: null,
    image_url: card.image_url,
    type: 'simple',
    sku: null,
    status: 'active',
    is_active: true,
    sort_order: 0,
    has_variants: variant !== null,
    batch_tracked: card.batch_tracked,
    created_at: '',
    updated_at: '',
    variants: variant
      ? [
          {
            id: variant.id,
            uuid: variant.uuid,
            product_id: card.id,
            name: variant.name,
            sku: null,
            price: variant.price,
            unit: variant.unit,
            // an unknown stock is not limited here: the server checks it at checkout
            stock: variant.stock ?? Number.POSITIVE_INFINITY,
            attributes_json: null,
            is_active: true,
            created_at: '',
            updated_at: '',
          },
        ]
      : [],
    modifier_groups: [],
    combo: null,
    price: card.price ?? undefined,
    store: undefined,
  };
}

// Whether a card can go into the cart without the full product: no options to pick, and its one variant is there
export function addsDirectly(card: Pick<ProductCardItem, 'needs_options' | 'variant'>): boolean {
  return !card.needs_options && card.variant !== null;
}

// false only when the server said so (a store list or a menu without the field: unknown, the cart and checkout check)
export function isAcceptingOrders(store: { accepting_orders?: boolean } | null | undefined): boolean {
  return store?.accepting_orders !== false;
}

// The cards whose name, description or restaurant matches the search (case-insensitive)
export function searchCards(cards: readonly ProductCardItem[], query: string): ProductCardItem[] {
  const q = query.trim().toLowerCase();
  if (!q) return [...cards];
  return cards.filter(
    (card) =>
      card.name.toLowerCase().includes(q) ||
      (card.description ?? '').toLowerCase().includes(q) ||
      (card.store?.name ?? '').toLowerCase().includes(q),
  );
}

export function searchStores(stores: readonly StoreListItem[], query: string, withDescription = false): StoreListItem[] {
  const q = query.trim().toLowerCase();
  if (!q) return [...stores];
  return stores.filter((store) => store.name.toLowerCase().includes(q) || (withDescription && (store.description ?? '').toLowerCase().includes(q)));
}

// The next page to ask for after `meta`, or undefined at the end. A server without paging (no meta) sent everything.
export function nextStorePage(meta: StorePageMeta | undefined | null): number | undefined {
  if (!meta) return undefined;
  return meta.page < meta.last_page ? meta.page + 1 : undefined;
}

// The stores of the loaded pages, each once (a store may move between pages while the list is being scrolled)
export function storesOfPages(pages: ReadonlyArray<{ stores: StoreListItem[] }> | undefined): StoreListItem[] {
  const seen = new Set<number>();
  const stores: StoreListItem[] = [];
  for (const page of pages ?? []) {
    for (const store of page.stores) {
      if (seen.has(store.id)) continue;
      seen.add(store.id);
      stores.push(store);
    }
  }
  return stores;
}
