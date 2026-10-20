import type { CartModifier } from '../store/cartStore';
import type { Modifier, ModifierGroup } from '../types';
import { toAmount } from './money';

// Choosing modifiers ("Sous", "Qo'shimchalar") on a product. The rules are the ones the server checks again at
// checkout (OrderLineBuilder::resolveModifiers): per group at least `min_select` and at most `max_select` choices (a
// modifier may be taken several times up to its `max_quantity`; every take counts), a required group has a minimum of
// one. The groups of the catalog already carry the effective rules of the product.

// How many times each modifier is taken: modifier id -> count (a modifier that is not taken is absent).
export type ModifierSelection = Record<number, number>;

// The effective minimum of a group: a required group needs at least one choice.
export function minSelectOf(group: Pick<ModifierGroup, 'min_select' | 'is_required'>): number {
  return group.is_required ? Math.max(1, group.min_select) : group.min_select;
}

// "Choose one" groups are radio buttons; the others check boxes.
export function isSingleChoice(group: Pick<ModifierGroup, 'max_select'>): boolean {
  return group.max_select === 1;
}

export function groupsOf(product: { modifier_groups?: ModifierGroup[] | null }): ModifierGroup[] {
  return [...(product.modifier_groups ?? [])]
    .filter((group) => group.modifiers.length > 0)
    .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
}

export function sortedModifiers(group: Pick<ModifierGroup, 'modifiers'>): Modifier[] {
  return [...group.modifiers].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
}

// How many choices the selection holds in the group.
export function countInGroup(group: Pick<ModifierGroup, 'modifiers'>, selection: ModifierSelection): number {
  return group.modifiers.reduce((sum, modifier) => sum + (selection[modifier.id] ?? 0), 0);
}

// The most one modifier can be taken: its own limit, and what its group still has room for.
function roomFor(group: ModifierGroup, modifier: Modifier, selection: ModifierSelection): number {
  const own = Math.max(1, modifier.max_quantity) - (selection[modifier.id] ?? 0);
  const inGroup = group.max_select === null ? Number.POSITIVE_INFINITY : group.max_select - countInGroup(group, selection);
  return Math.min(own, inGroup);
}

function without(selection: ModifierSelection, id: number): ModifierSelection {
  return Object.fromEntries(Object.entries(selection).filter(([key]) => Number(key) !== id));
}

// Taps a modifier. In a "choose one" group it replaces the group's choice (and, when the group is optional, taps off
// the one already chosen); in the others it checks or unchecks it, unless the group is full.
// `isAvailable` lets the caller keep a stopped modifier from being chosen.
export function toggleModifier(group: ModifierGroup, selection: ModifierSelection, modifier: Modifier): ModifierSelection {
  const chosen = (selection[modifier.id] ?? 0) > 0;

  if (isSingleChoice(group)) {
    let next = selection;
    for (const other of group.modifiers) next = without(next, other.id);
    if (chosen) return minSelectOf(group) > 0 ? selection : next;
    return { ...next, [modifier.id]: 1 };
  }

  if (chosen) return without(selection, modifier.id);
  return roomFor(group, modifier, selection) >= 1 ? { ...selection, [modifier.id]: 1 } : selection;
}

// "+" / "-" of a modifier that may be taken more than once.
export function stepModifier(group: ModifierGroup, selection: ModifierSelection, modifier: Modifier, delta: 1 | -1): ModifierSelection {
  const count = selection[modifier.id] ?? 0;
  if (delta < 0) return count <= 1 ? without(selection, modifier.id) : { ...selection, [modifier.id]: count - 1 };
  return roomFor(group, modifier, selection) >= 1 ? { ...selection, [modifier.id]: count + 1 } : selection;
}

// The Uzbek line that says what is wrong with a group's choice, or null when it is fine.
export function groupProblem(group: ModifierGroup, selection: ModifierSelection): string | null {
  const count = countInGroup(group, selection);
  const min = minSelectOf(group);
  if (count < min) {
    return min === 1 ? `"${group.name}" tanlang` : `"${group.name}": kamida ${min} ta tanlang`;
  }
  if (group.max_select !== null && count > group.max_select) {
    return `"${group.name}": ko'pi bilan ${group.max_select} ta tanlash mumkin`;
  }
  return null;
}

// The first thing that stops the product from being added, or null when the selection is complete.
export function selectionProblem(groups: readonly ModifierGroup[], selection: ModifierSelection): string | null {
  for (const group of groups) {
    const problem = groupProblem(group, selection);
    if (problem) return problem;
  }
  return null;
}

// What a group asks for, shown under its name: "Majburiy · 1 ta tanlang", "Ixtiyoriy · ko'pi bilan 3 ta".
export function groupRule(group: ModifierGroup): string {
  const min = minSelectOf(group);
  const max = group.max_select;
  const required = min > 0 ? 'Majburiy' : 'Ixtiyoriy';
  if (max === 1) return min > 0 ? 'Majburiy · 1 ta tanlang' : 'Ixtiyoriy · 1 tagacha';
  if (max !== null && min > 0 && min === max) return `${required} · ${max} ta tanlang`;
  if (max !== null && min > 0) return `${required} · ${min}–${max} ta tanlang`;
  if (max !== null) return `${required} · ko'pi bilan ${max} ta`;
  if (min > 0) return `${required} · kamida ${min} ta`;
  return required;
}

// The choices as the cart keeps them, in the order the groups and modifiers are shown.
export function toCartModifiers(groups: readonly ModifierGroup[], selection: ModifierSelection): CartModifier[] {
  return groups.flatMap((group) =>
    sortedModifiers(group).flatMap((modifier) => {
      const quantity = selection[modifier.id] ?? 0;
      return quantity > 0
        ? [{ id: modifier.id, uuid: modifier.uuid, groupId: group.id, name: modifier.name, priceDelta: toAmount(modifier.price_delta), quantity }]
        : [];
    }),
  );
}

// What the POST /orders body carries for the choices: `[{id: uuid, quantity}]`.
export function toOrderModifiers(modifiers: readonly CartModifier[] | undefined): Array<{ id: string; quantity: number }> {
  return (modifiers ?? []).map((modifier) => ({ id: modifier.uuid, quantity: modifier.quantity }));
}
