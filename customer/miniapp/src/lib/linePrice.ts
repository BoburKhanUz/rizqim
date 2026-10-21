import type { MenuUnit } from '../types';
import { isMeasuredUnit } from './quantity';

// The price of one order line, computed exactly like the server (App\Services\Order\OrderLineBuilder::lineTotal):
//
//   line = round_half_up_to_whole_som( unit price × quantity + modifiers × (pieces ? quantity : 1) )
//
// The unit price is per piece, or per kg / l for an item sold by weight; "modifiers" is the sum of the chosen
// modifiers' price deltas × their counts, added to every piece, or once to a weighed line. Everything is done in
// integers (tiyin for money, thousandths for quantities) and rounded ONCE at the end, so 33 333 × 0.5 is 16 667 and not
// whatever a float says. The server stays the authority: this is the preview the customer sees before the order.

const TIYIN = 100;
const MILLI = 1000;

export interface PricedModifier {
  priceDelta: number;
  quantity: number;
}

// What the chosen modifiers add to one piece (or to the whole weighed line), in so'm with tiyin.
export function modifiersTotal(modifiers: readonly PricedModifier[] | undefined | null): number {
  if (!modifiers || modifiers.length === 0) return 0;
  const tiyin = modifiers.reduce((sum, modifier) => sum + Math.round(modifier.priceDelta * TIYIN) * modifier.quantity, 0);
  return tiyin / TIYIN;
}

// Whole so'm, half rounded up (away from zero for the rare negative total, like Brick's HALF_UP).
function roundHalfUp(numerator: bigint, denominator: bigint): bigint {
  const negative = numerator < 0n;
  const abs = negative ? -numerator : numerator;
  const rounded = (abs * 2n + denominator) / (denominator * 2n);
  return negative ? -rounded : rounded;
}

export interface LineInput {
  price: number; // per unit
  quantity: number;
  unit?: MenuUnit | null;
  modifiers?: readonly PricedModifier[] | null;
}

export function lineTotal({ price, quantity, unit, modifiers }: LineInput): number {
  const priceTiyin = BigInt(Math.round(price * TIYIN));
  const quantityMilli = BigInt(Math.round(quantity * MILLI));
  const modifiersTiyin = BigInt(Math.round(modifiersTotal(modifiers) * TIYIN));

  // Scale: tiyin × thousandths
  const exact = priceTiyin * quantityMilli + modifiersTiyin * (isMeasuredUnit(unit) ? BigInt(MILLI) : quantityMilli);
  return Number(roundHalfUp(exact, BigInt(TIYIN * MILLI)));
}
