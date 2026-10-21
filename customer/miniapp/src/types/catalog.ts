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

// How a variant is sold (product_variants.unit, App\Enums\MenuUnit): per piece, or per kilogram / litre.
export type MenuUnit = 'piece' | 'kg' | 'l';

export interface ProductVariant {
  id: number;
  uuid: string;
  product_id: number;
  name: string | null;
  sku: string | null;
  price: Decimal; // a DECIMAL column, so usually a string ("12000.00"); the channel's price per unit (per kg for a weighed item)
  base_price?: Decimal; // the variant's own price when a channel price overrides `price`
  unit?: MenuUnit; // missing on an older backend: pieces
  quantity_step?: Decimal | null; // kg / l only: the step a quantity moves in ("0.100" when unset the server uses 0.1)
  min_quantity?: Decimal | null; // kg / l only: the least one line may take
  stock: number; // a fraction for items sold by weight
  attributes_json: Record<string, unknown> | null;
  is_active: boolean | number; // boolean on PostgreSQL; legacy drivers send 0/1
  created_at: string;
  updated_at: string;
}

// One choice of a modifier group (App\Models\Modifier) with the price it adds.
export interface Modifier {
  id: number;
  uuid: string;
  modifier_group_id: number;
  name: string;
  price_delta: Decimal; // added per piece, or once per line for an item sold by weight
  max_quantity: number; // how many times one item may take it
  sort_order: number;
  is_active: boolean;
}

// A group of modifiers as one product offers it: the rules are the effective ones for that product
// (CatalogPresenter merges the group's with the product's own).
export interface ModifierGroup {
  id: number;
  uuid: string;
  name: string;
  min_select: number;
  max_select: number | null; // null: any number
  is_required: boolean;
  sort_order: number;
  modifiers: Modifier[];
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
  batch_tracked?: boolean; // sold from a batch ("qozon"): sold out while the branch has no open batch
  created_at: string;
  updated_at: string;
  variants: ProductVariant[];
  modifier_groups?: ModifierGroup[]; // active groups with their active modifiers; missing on an older backend
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

// What cannot be ordered on one branch right now (GET /catalog/availability, MenuAvailability::forStore).
export interface BranchAvailability {
  branch_id: number;
  stopped: { products: number[]; variants: number[]; modifiers: number[] };
  sold_out: { variants: number[] }; // batch stock ("tugadi")
  stop_list?: Array<{ id: number | null; type: string; subject_id: number; reason: string | null; until: string | null }>;
  batches?: unknown[];
}

// GET /catalog/availability?store_id=...
export interface MenuAvailabilityData {
  store_id: number;
  branches: BranchAvailability[];
  generated_at?: string;
}

// GET /catalog?store_id=... (one store).
export interface StoreCatalog {
  store: StoreCatalogStore;
  categories: Category[];
  products: Product[];
  availability?: BranchAvailability[]; // the same as GET /catalog/availability, `branches`
}
