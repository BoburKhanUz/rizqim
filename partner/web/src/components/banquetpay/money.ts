// Sums decimal so'm strings exactly (in tiyin), for the schedule editor's running total; the server checks the sum.
export function sumAmounts(values: readonly string[]): number {
  let tiyin = 0;
  for (const value of values) {
    const number = Number(value.trim().replace(/\s+/g, '').replace(',', '.'));
    if (Number.isFinite(number)) tiyin += Math.round(number * 100);
  }
  return tiyin / 100;
}
