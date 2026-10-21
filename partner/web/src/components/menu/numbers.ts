// Number fields are text inputs (a comma or a point as the person types it); these read them. The server decides what
// is valid: this only turns the text into the number the API takes, or says it is not a number.

// "35 000", "0,5" and "0.5" -> 35000, 0.5; "" and anything else -> null
export function parseDecimal(text: string): number | null {
  const cleaned = text.replace(/[\s ]/g, '').replace(',', '.');
  if (cleaned === '' || !/^\d*\.?\d+$|^\d+\.$/.test(cleaned)) return null;
  const value = Number(cleaned);
  return Number.isFinite(value) ? value : null;
}

// A number from the API as the text of an input: 0.5 -> "0,5" (no trailing zeros), "35000.00" -> "35000"
export function toInputText(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '';
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value);
  return String(number).replace('.', ',');
}
