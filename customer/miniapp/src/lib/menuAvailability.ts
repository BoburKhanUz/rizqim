import { quantityOfVariant, type CartItem } from '../store/cartStore';
import type { BranchAvailability, Modifier, Product, ProductVariant } from '../types';
import { formatQuantity } from './quantity';
import { getVariantStock, isVariantActive, isVariantOutOfStock } from './stock';

// What the customer cannot order right now (docs/MENU.md, "Mavjudlik"): the stop-list, the per-branch switches and
// batches that ran out ("tugadi"), as GET /catalog/availability reports them per branch.
//
// The customer does not choose the branch (delivery is routed to one by the address, pickup is picked in the cart), so
// before the branch is known something counts as unavailable only when EVERY active branch is out of it: otherwise some
// branch still cooks it and the order can go through. Once the branch is known (the delivery quote's, or the pickup
// branch) the view is that branch's alone. The server checks again at checkout, whatever this says.

export interface AvailabilityView {
  // Whether any availability data is behind it (without it nothing is flagged: the server decides at checkout)
  known: boolean;
  isProductStopped: (productId: number) => boolean;
  isVariantStopped: (productId: number, variantId: number) => boolean;
  isModifierStopped: (modifierId: number) => boolean;
}

const NOTHING_STOPPED: AvailabilityView = {
  known: false,
  isProductStopped: () => false,
  isVariantStopped: () => false,
  isModifierStopped: () => false,
};

// The view of one branch, or (no `branchId`, or one the data does not list) of the whole store.
export function availabilityView(branches: readonly BranchAvailability[] | null | undefined, branchId?: number | null): AvailabilityView {
  if (!branches || branches.length === 0) return NOTHING_STOPPED;
  const own = branchId != null ? branches.filter((branch) => branch.branch_id === branchId) : [];
  const scope = own.length > 0 ? own : branches;

  const everyBranch = (stoppedOn: (branch: BranchAvailability) => boolean) => scope.every(stoppedOn);
  return {
    known: true,
    isProductStopped: (productId) => everyBranch((branch) => branch.stopped.products.includes(productId)),
    isVariantStopped: (productId, variantId) =>
      everyBranch(
        (branch) =>
          branch.stopped.products.includes(productId) ||
          branch.stopped.variants.includes(variantId) ||
          branch.sold_out.variants.includes(variantId),
      ),
    isModifierStopped: (modifierId) => everyBranch((branch) => branch.stopped.modifiers.includes(modifierId)),
  };
}

// A variant that cannot be ordered: switched off by the store, known to be out of stock, or stopped on the branch(es).
export function isVariantUnavailable(product: Pick<Product, 'id'>, variant: ProductVariant, view: AvailabilityView): boolean {
  return !isVariantActive(variant) || isVariantOutOfStock(variant) || view.isVariantStopped(product.id, variant.id);
}

// A product with nothing left to order: stopped as a whole, or every variant unavailable. (A product without variants is
// not "sold out" by this: it has nothing to sell and the store page shows it as before.)
export function isProductUnavailable(product: Product, view: AvailabilityView): boolean {
  if (view.isProductStopped(product.id)) return true;
  const variants = product.variants ?? [];
  return variants.length > 0 && variants.every((variant) => isVariantUnavailable(product, variant, view));
}

export function isModifierUnavailable(modifier: Pick<Modifier, 'id'>, view: AvailabilityView): boolean {
  return view.isModifierStopped(modifier.id);
}

// Why a cart line cannot be ordered any more.
export type LineProblem = 'sold_out' | 'modifier_stopped' | 'removed' | 'insufficient';

export interface CartLineIssue {
  item: CartItem;
  problem: LineProblem;
  // The Uzbek line shown under the dish
  message: string;
}

// The cart lines that would be refused now, against the store's current menu (`products`, GET /catalog?store_id=) and
// availability. `products` is null until the menu has loaded: nothing is flagged on a guess.
export function findCartIssues(items: readonly CartItem[], products: readonly Product[] | null, view: AvailabilityView): CartLineIssue[] {
  if (products === null) return [];
  const issues: CartLineIssue[] = [];

  for (const item of items) {
    const product = products.find((candidate) => candidate.id === item.productId);
    const variant = product?.variants?.find((candidate) => candidate.uuid === item.variantId);

    if (!product || (item.variantId && !variant)) {
      issues.push({ item, problem: 'removed', message: "Bu taom menyudan olib tashlangan" });
      continue;
    }
    if (isProductUnavailable(product, view) || (variant && isVariantUnavailable(product, variant, view))) {
      issues.push({ item, problem: 'sold_out', message: 'Tugadi' });
      continue;
    }
    const stock = getVariantStock(variant);
    if (variant && stock !== null && quantityOfVariant(items, item.variantId) > stock) {
      issues.push({ item, problem: 'insufficient', message: `Omborda faqat ${formatQuantity(stock, item.unit)} qolgan` });
      continue;
    }
    const stopped = item.modifiers?.find((modifier) => view.isModifierStopped(modifier.id));
    if (stopped) {
      issues.push({ item, problem: 'modifier_stopped', message: `"${stopped.name}" tugadi: boshqasini tanlang yoki olib tashlang` });
    }
  }

  return issues;
}

// The view of a menu whose availability is not known: nothing is flagged.
export const NO_AVAILABILITY: AvailabilityView = NOTHING_STOPPED;
