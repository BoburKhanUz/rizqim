import { describe, expect, it } from 'vitest';
import type { Modifier, ModifierGroup } from '../types';
import { groupProblem, groupRule, selectionProblem, stepModifier, toCartModifiers, toOrderModifiers, toggleModifier } from './modifiers';

function modifier(id: number, name: string, delta = '0.00', extra: Partial<Modifier> = {}): Modifier {
  return { id, uuid: `m-${id}`, modifier_group_id: 1, name, price_delta: delta, max_quantity: 1, sort_order: id, is_active: true, ...extra };
}

function group(overrides: Partial<ModifierGroup> = {}): ModifierGroup {
  return {
    id: 1,
    uuid: 'g-1',
    name: 'Sous',
    min_select: 0,
    max_select: null,
    is_required: false,
    sort_order: 0,
    modifiers: [modifier(1, 'Ketchup'), modifier(2, 'Pishloqli', '3000.00'), modifier(3, 'Achchiq', '1000.00')],
    ...overrides,
  };
}

const single = () => group({ is_required: true, min_select: 1, max_select: 1 });

describe('a choose-one group', () => {
  it('replaces the choice', () => {
    const g = single();
    let selection = toggleModifier(g, {}, g.modifiers[0]);
    expect(selection).toEqual({ 1: 1 });
    selection = toggleModifier(g, selection, g.modifiers[1]);
    expect(selection).toEqual({ 2: 1 });
  });

  it('cannot be tapped off while the group is required', () => {
    const g = single();
    expect(toggleModifier(g, { 1: 1 }, g.modifiers[0])).toEqual({ 1: 1 });
  });

  it('can be tapped off when it is optional', () => {
    const g = group({ max_select: 1 });
    expect(toggleModifier(g, { 1: 1 }, g.modifiers[0])).toEqual({});
  });
});

describe('a multiple-choice group', () => {
  it('checks and unchecks', () => {
    const g = group();
    let selection = toggleModifier(g, {}, g.modifiers[0]);
    selection = toggleModifier(g, selection, g.modifiers[1]);
    expect(selection).toEqual({ 1: 1, 2: 1 });
    expect(toggleModifier(g, selection, g.modifiers[0])).toEqual({ 2: 1 });
  });

  it('stops at max_select', () => {
    const g = group({ max_select: 2 });
    const full = { 1: 1, 2: 1 };
    expect(toggleModifier(g, full, g.modifiers[2])).toEqual(full);
  });

  it('takes a modifier more than once up to its max_quantity, every take counting against the group', () => {
    const g = group({ max_select: 3, modifiers: [modifier(1, 'Pishloq', '2000.00', { max_quantity: 2 }), modifier(2, 'Go\'sht', '9000.00')] });
    let selection = toggleModifier(g, {}, g.modifiers[0]);
    selection = stepModifier(g, selection, g.modifiers[0], 1);
    expect(selection).toEqual({ 1: 2 });
    expect(stepModifier(g, selection, g.modifiers[0], 1)).toEqual({ 1: 2 }); // its own limit
    selection = toggleModifier(g, selection, g.modifiers[1]);
    expect(selection).toEqual({ 1: 2, 2: 1 });
    expect(stepModifier(g, selection, g.modifiers[0], -1)).toEqual({ 1: 1, 2: 1 });
  });
});

describe('what stops the product from being added', () => {
  it('a required group without a choice', () => {
    const g = single();
    expect(groupProblem(g, {})).toBe('"Sous" tanlang');
    expect(groupProblem(g, { 1: 1 })).toBeNull();
    expect(selectionProblem([g], {})).toBe('"Sous" tanlang');
    expect(selectionProblem([g], { 2: 1 })).toBeNull();
  });

  it('a group asking for more than one', () => {
    const g = group({ min_select: 2, max_select: 3 });
    expect(groupProblem(g, { 1: 1 })).toBe('"Sous": kamida 2 ta tanlang');
    expect(groupProblem(g, { 1: 1, 2: 1 })).toBeNull();
  });

  it('is_required means at least one even when min_select says 0', () => {
    expect(groupProblem(group({ is_required: true }), {})).toBe('"Sous" tanlang');
  });

  it('an optional group is never a problem', () => {
    expect(selectionProblem([group()], {})).toBeNull();
  });
});

it('says what a group asks for', () => {
  expect(groupRule(single())).toBe('Majburiy · 1 ta tanlang');
  expect(groupRule(group())).toBe('Ixtiyoriy');
  expect(groupRule(group({ max_select: 3 }))).toBe("Ixtiyoriy · ko'pi bilan 3 ta");
  expect(groupRule(group({ min_select: 1, max_select: 3, is_required: true }))).toBe('Majburiy · 1–3 ta tanlang');
  expect(groupRule(group({ max_select: 1 }))).toBe('Ixtiyoriy · 1 tagacha');
});

describe('the choices as the cart and the order carry them', () => {
  it('keep the group and modifier order and the price delta as a number', () => {
    const g = group();
    const chosen = toCartModifiers([g], { 3: 1, 2: 2 });
    expect(chosen).toEqual([
      { id: 2, uuid: 'm-2', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 2 },
      { id: 3, uuid: 'm-3', groupId: 1, name: 'Achchiq', priceDelta: 1000, quantity: 1 },
    ]);
    expect(toOrderModifiers(chosen)).toEqual([{ id: 'm-2', quantity: 2 }, { id: 'm-3', quantity: 1 }]);
    expect(toOrderModifiers(undefined)).toEqual([]);
  });
});
