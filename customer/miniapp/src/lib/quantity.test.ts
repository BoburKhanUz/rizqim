import { describe, expect, it } from 'vitest';
import {
  addQuantities,
  formatLineQuantity,
  formatQuantity,
  isMeasuredUnit,
  isMultipleOfStep,
  minQuantityOf,
  quantityStepOf,
  subtractQuantities,
} from './quantity';

describe('arithmetic in thousandths', () => {
  it('adds and subtracts without float noise', () => {
    expect(addQuantities(0.1, 0.2)).toBe(0.3);
    expect(subtractQuantities(1.5, 0.4)).toBe(1.1);
  });

  it('knows a multiple of the step', () => {
    expect(isMultipleOfStep(0.3, 0.1)).toBe(true);
    expect(isMultipleOfStep(0.25, 0.1)).toBe(false);
    expect(isMultipleOfStep(1.5, 0.5)).toBe(true);
  });
});

describe('the step and the least quantity of a variant', () => {
  it('moves pieces by one', () => {
    expect(quantityStepOf({ unit: 'piece', quantity_step: null })).toBe(1);
    expect(quantityStepOf({})).toBe(1);
    expect(minQuantityOf({})).toBe(1);
  });

  it('uses the variant\'s step and minimum for kg and l', () => {
    expect(quantityStepOf({ unit: 'kg', quantity_step: '0.500' })).toBe(0.5);
    expect(minQuantityOf({ unit: 'kg', quantity_step: '0.500', min_quantity: '1.000' })).toBe(1);
  });

  it('defaults like the server: a step of 0.1, a minimum of one step', () => {
    expect(quantityStepOf({ unit: 'kg', quantity_step: null })).toBe(0.1);
    expect(minQuantityOf({ unit: 'l', quantity_step: '0.250', min_quantity: null })).toBe(0.25);
  });

  it('knows the measured units', () => {
    expect(isMeasuredUnit('kg')).toBe(true);
    expect(isMeasuredUnit('l')).toBe(true);
    expect(isMeasuredUnit('piece')).toBe(false);
    expect(isMeasuredUnit(undefined)).toBe(false);
  });
});

describe('formatting', () => {
  const NBSP = '\u00a0';

  it('writes 1,5 kg in Uzbek and Russian, 1.5 kg in English', () => {
    expect(formatQuantity(1.5, 'kg', 'uz')).toBe(`1,5${NBSP}kg`);
    expect(formatQuantity(1.5, 'kg', 'ru')).toBe(`1,5${NBSP}кг`);
    expect(formatQuantity(1.5, 'kg', 'en')).toBe(`1.5${NBSP}kg`);
    expect(formatQuantity(0.25, 'l', 'uz')).toBe(`0,25${NBSP}l`);
  });

  it('writes pieces as a bare number', () => {
    expect(formatQuantity(2, 'piece', 'uz')).toBe('2');
    expect(formatQuantity(2, undefined, 'uz')).toBe('2');
  });

  it('shows no trailing zeros or float noise', () => {
    expect(formatQuantity(0.1 + 0.2, 'kg', 'uz')).toBe(`0,3${NBSP}kg`);
    expect(formatQuantity(2, 'kg', 'uz')).toBe(`2${NBSP}kg`);
  });

  it('writes the quantity column of an order line', () => {
    expect(formatLineQuantity(2, 'piece', 'uz')).toBe('x2');
    expect(formatLineQuantity(1.5, 'kg', 'uz')).toBe(`1,5${NBSP}kg`);
  });
});
