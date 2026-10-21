import {
  formatLineQuantity as formatLineQuantityIn,
  formatQuantity as formatQuantityIn,
  formatQuantityNumber as formatQuantityNumberIn,
  isMeasuredUnit,
  roundQuantity,
  unitLabel as unitLabelIn,
} from '@rizqim/staff';
import { useLanguageStore, type Language } from '../store/languageStore';
import type { MenuUnit, ProductVariant } from '../types';

// Quantities of the menu (docs/MENU.md): pieces are whole, an item sold by kilogram or litre moves in steps
// (0.5 kg) and has at most three decimals, exactly like App\Support\Quantity. All arithmetic is done in thousandths so
// that 0.1 + 0.2 is 0.3 and not 0.30000000000000004.

const SCALE = 1000;

// A quantity as a whole number of thousandths (rounded to the nearest, so a float's noise vanishes).
const toMilli = (quantity: number): number => Math.round(quantity * SCALE);

export function addQuantities(a: number, b: number): number {
  return (toMilli(a) + toMilli(b)) / SCALE;
}

export function subtractQuantities(a: number, b: number): number {
  return (toMilli(a) - toMilli(b)) / SCALE;
}

// How a quantity moves for a variant: a piece by 1, a measured item by `quantity_step` (0.1 when the server sends none,
// like ProductVariant::quantityStep).
export function quantityStepOf(variant: Pick<ProductVariant, 'unit' | 'quantity_step'> | undefined | null): number {
  if (!isMeasuredUnit(variant?.unit)) return 1;
  const step = Number(variant?.quantity_step);
  return Number.isFinite(step) && step > 0 ? roundQuantity(step) : 0.1;
}

// The least one line may take: `min_quantity` for a measured item (else one step), 1 for pieces (ProductVariant::minimumQuantity).
// The server also wants every quantity to be a multiple of the step (OrderLineBuilder::checkQuantity), so a minimum that
// is not one (0.3 kg in steps of 0.5, saved before the menu forms checked it) becomes the first multiple above it (0.5):
// starting there and moving by the step, every quantity offered is one the server takes.
export function minQuantityOf(variant: Pick<ProductVariant, 'unit' | 'quantity_step' | 'min_quantity'> | undefined | null): number {
  if (!isMeasuredUnit(variant?.unit)) return 1;
  const step = quantityStepOf(variant);
  const min = Number(variant?.min_quantity);
  if (!Number.isFinite(min) || min <= 0) return step;
  const stepMilli = toMilli(step);
  return (Math.ceil(toMilli(min) / stepMilli) * stepMilli) / SCALE;
}

// The step as the server wants it: a multiple of `step` (checked in thousandths, like Quantity::isMultipleOf).
export function isMultipleOfStep(quantity: number, step: number): boolean {
  const stepMilli = toMilli(step);
  return stepMilli <= 0 || toMilli(quantity) % stepMilli === 0;
}

// Printing a quantity is @rizqim/staff's (shared with the partner web); these default the language to the user's.
const currentLanguage = (): Language => useLanguageStore.getState().language;

export { isMeasuredUnit, roundQuantity };

export function unitLabel(unit: MenuUnit | undefined | null, language: Language = currentLanguage()): string {
  return unitLabelIn(unit, language);
}

// The number of a quantity as written in the language: "1,5" in uz and ru, "1.5" in en; no trailing zeros.
export function formatQuantityNumber(quantity: number, language: Language = currentLanguage()): string {
  return formatQuantityNumberIn(quantity, language);
}

// "1,5 kg" for an item sold by weight, "2" for pieces (a piece count carries no unit, as before weights existed).
export function formatQuantity(quantity: number, unit?: MenuUnit | null, language: Language = currentLanguage()): string {
  return formatQuantityIn(quantity, unit, language);
}

// The quantity column of an order line: "x2" for pieces, "1,5 kg" for an item sold by weight.
export function formatLineQuantity(quantity: number, unit?: MenuUnit | null, language: Language = currentLanguage()): string {
  return formatLineQuantityIn(quantity, unit, language);
}
