import type { Decimal } from './api';
import type { StoreBase, StoreCatalogStore } from './store';

export interface Category {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  parent_id: number | null;
  image: string | null;
  image_url: string | null;
}

// A category in the owner's product form dropdown (GET /owner/categories).
export interface CategoryOption {
  id: number;
  name: string;
  image: string | null;
  image_url: string | null;
}

export interface ProductVariant {
  id: number;
  uuid: string;
  product_id: number;
  name: string | null;
  sku: string | null;
  price: Decimal; // a DECIMAL column, so usually a string ("12000.00")
  stock: number;
  attributes_json: Record<string, unknown> | null;
  is_active: boolean | number; // boolean on PostgreSQL; legacy drivers send 0/1
  created_at: string;
  updated_at: string;
}

// Mirrors the values the backend writes to products.status.
export type ProductStatus = 'active' | 'inactive' | 'draft' | 'out_of_stock';

export interface Product {
  id: number;
  uuid: string;
  store_id: number;
  category_id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  image_url: string | null;
  type: string;
  sku: string | null;
  status: ProductStatus;
  is_active: boolean;
  sort_order: number;
  has_variants: boolean;
  created_at: string;
  updated_at: string;
  variants: ProductVariant[];
  store?: StoreBase; // only on the global feed (GET /catalog without store_id)
  category?: Category | null; // only on the owner's list (GET /owner/products)
  price?: Decimal; // not sent by the API: the UI falls back to the cheapest variant
}

// A banner of the home slider.
export interface Promo {
  id: number;
  title: string;
  image_url: string | null;
  store_uuid: string | null;
  product_id: number | null;
}

// GET /catalog (global feed).
export interface FeedCatalog {
  categories: Category[];
  products: Product[];
  promos: Promo[];
  highlight_product: Product | null;
}

// GET /catalog?store_id=... (one store).
export interface StoreCatalog {
  store: StoreCatalogStore;
  categories: Category[];
  products: Product[];
}
