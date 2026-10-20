// Money helpers: the implementation is shared with the partner web (packages/ts/ui/src/lib/money.ts).
// Money comes from the API as decimal strings ("15000.00") and from the app's own arithmetic as numbers. `+` on
// two strings concatenates, and a stray NaN ends up on screen as "NaN so'm": every amount goes through here first.
export { parseAmount, sumAmounts, toAmount } from '@rizqim/ui';
