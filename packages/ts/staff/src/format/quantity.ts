import { NBSP, type Language } from '@rizqim/ui';
import type { MenuUnit } from '../types';

// How the quantity of an order line reads (docs/MENU.md): pieces are whole, an item sold by kilogram or litre has a
// fraction of at most three decimals. Arithmetic on quantities (steps, minimums) is the customer app's business and
// stays there; the staff screens only print them. Rounded to thousandths so float noise (0.1 + 0.2) never shows.

const SCALE = 1000;

// The quantity with the float noise of arithmetic removed (three decimals).
export function roundQuantity(quantity: number): number {
  return Math.round(quantity * SCALE) / SCALE;
}

export function isMeasuredUnit(unit: MenuUnit | undefined | null): boolean {
  return unit === 'kg' || unit === 'l';
}

const UNIT_LABEL: Record<Language, Record<MenuUnit, string>> = {
  uz: { piece: 'dona', kg: 'kg', l: 'l' },
  ru: { piece: 'шт', kg: 'кг', l: 'л' },
  en: { piece: 'pcs', kg: 'kg', l: 'l' },
};

export function unitLabel(unit: MenuUnit | undefined | null, language: Language): string {
  return UNIT_LABEL[language][unit ?? 'piece'];
}

// The number of a quantity as written in the language: "1,5" in uz and ru, "1.5" in en; no trailing zeros.
export function formatQuantityNumber(quantity: number, language: Language): string {
  const text = String(roundQuantity(quantity));
  return language === 'en' ? text : text.replace('.', ',');
}

// "1,5 kg" for an item sold by weight, "2" for pieces (a piece count carries no unit). The space is non-breaking so
// the number and its unit never wrap apart.
export function formatQuantity(quantity: number, unit: MenuUnit | null | undefined, language: Language): string {
  const number = formatQuantityNumber(quantity, language);
  return isMeasuredUnit(unit) ? `${number}${NBSP}${unitLabel(unit, language)}` : number;
}

// The quantity column of an order line: "x2" for pieces, "1,5 kg" for an item sold by weight.
export function formatLineQuantity(quantity: number, unit: MenuUnit | null | undefined, language: Language): string {
  return isMeasuredUnit(unit) ? formatQuantity(quantity, unit, language) : `x${quantity}`;
}
