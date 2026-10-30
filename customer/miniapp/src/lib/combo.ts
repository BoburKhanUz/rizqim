import type { ComboOption, ComboSlot, Product, ProductCombo } from '../types';
import type { AvailabilityView } from './menuAvailability';
import { toAmount } from './money';

// P6-31: picking a combo's parts on the product sheet (docs/MENU.md "Kombo"). The rules are the server's
// (App\Domain\Pos\Menu\Combos::resolve), which checks the order again: every required slot filled (a slot left alone
// takes its default), no more picks than the slot allows, only options the branch can sell now (an unavailable option
// is not offered). The price of one combo is its own plus the deltas of the options picked.

// The picks of one combo: slot id => option uuids (one per pick)
export type ComboSelection = Record<number, string[]>;

// One part of a combo in the cart (and in POST /orders as {option_id})
export interface CartComboPart {
  optionUuid: string;
  slotName: string;
  name: string;
  label: string; // the receipt text, no price
  priceDelta: number; // so'm, what it adds to one combo
}

// An option the customer can pick: its dish is not stopped or sold out on the branch(es)
export function isOptionAvailable(option: ComboOption, view: AvailabilityView): boolean {
  if (option.product_id !== null && view.isProductStopped(option.product_id)) return false;
  if (option.product_id !== null && view.isVariantStopped(option.product_id, option.variant_id)) return false;
  return !option.modifiers.some((modifier) => view.isModifierStopped(modifier.id));
}

export const optionsOf = (slot: ComboSlot, view: AvailabilityView) => slot.options.filter((option) => isOptionAvailable(option, view));

// The sheet's first selection: each required slot's default (when it can be sold), nothing in the optional ones
export function defaultComboSelection(combo: ProductCombo, view: AvailabilityView): ComboSelection {
  const selection: ComboSelection = {};
  for (const slot of combo.slots) {
    const def = slot.options.find((option) => option.is_default && isOptionAvailable(option, view));
    selection[slot.id] = slot.min_picks > 0 && def ? [def.uuid] : [];
  }
  return selection;
}

// What keeps the combo from being added, in words; null when it can be
export function comboProblem(combo: ProductCombo, selection: ComboSelection, view: AvailabilityView): string | null {
  for (const slot of combo.slots) {
    const picked = selection[slot.id] ?? [];
    if (slot.min_picks > 0 && optionsOf(slot, view).length === 0) return `"${slot.name}" hozir mavjud emas`;
    if (picked.length < slot.min_picks) return slot.min_picks === 1 ? `"${slot.name}" ni tanlang` : `"${slot.name}" dan kamida ${slot.min_picks} ta tanlang`;
    if (picked.length > Math.max(1, slot.max_picks)) return `"${slot.name}" dan ko'pi bilan ${slot.max_picks} ta`;
  }
  return null;
}

// Picking an option in a slot: a one-pick slot replaces its pick, a slot of several toggles it (up to its maximum)
export function toggleOption(selection: ComboSelection, slot: ComboSlot, uuid: string): ComboSelection {
  const picked = selection[slot.id] ?? [];
  if (Math.max(1, slot.max_picks) === 1) return { ...selection, [slot.id]: slot.min_picks > 0 || !picked.includes(uuid) ? [uuid] : [] };
  if (picked.includes(uuid)) return { ...selection, [slot.id]: picked.filter((candidate) => candidate !== uuid) };
  if (picked.length >= slot.max_picks) return selection;
  return { ...selection, [slot.id]: [...picked, uuid] };
}

// The cart's parts of a selection, in slot order
export function toCartCombo(combo: ProductCombo, selection: ComboSelection): CartComboPart[] {
  return combo.slots.flatMap((slot) =>
    (selection[slot.id] ?? []).flatMap((uuid) => {
      const option = slot.options.find((candidate) => candidate.uuid === uuid);
      return option ? [{ optionUuid: option.uuid, slotName: slot.name, name: option.name, label: option.receipt_label, priceDelta: toAmount(option.price_delta) }] : [];
    }),
  );
}

// What the parts add to one combo (so'm, exact in tiyin)
export const comboDelta = (parts: readonly CartComboPart[] | undefined): number => (parts ?? []).reduce((sum, part) => sum + Math.round(part.priceDelta * 100), 0) / 100;

// POST /orders `items.*.combo`: the picks only, the server prices them
export function toOrderCombo(parts: readonly CartComboPart[]): Array<{ option_id: string; quantity?: number }> {
  const counts = new Map<string, number>();
  for (const part of parts) counts.set(part.optionUuid, (counts.get(part.optionUuid) ?? 0) + 1);
  return [...counts.entries()].map(([uuid, quantity]) => (quantity > 1 ? { option_id: uuid, quantity } : { option_id: uuid }));
}

export const isCombo = (product: Pick<Product, 'combo'>): boolean => product.combo != null && product.combo.slots.length > 0;
