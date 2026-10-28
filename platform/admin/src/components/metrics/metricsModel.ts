import { toAmount } from '@rizqim/ui';

// Ko'rsatkichlar: the small words and numbers the charts share. Months come as "YYYY-MM" (Asia/Tashkent).

const MONTHS = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avg', 'sen', 'okt', 'noy', 'dek'] as const;

// "2026-10" → "okt 2026"
export function monthLabel(month: string): string {
  const [year, number] = month.split('-');
  const name = MONTHS[Number(number) - 1];
  return name ? `${name} ${year}` : month;
}

// The label under a bar: "okt"; the first bar also has its year ("noy 2025", the year of the rest follows from it)
export function monthShort(month: string, index: number): string {
  const [year, number] = month.split('-');
  const name = MONTHS[Number(number) - 1] ?? month;
  return index === 0 ? `${name} ${year}` : name;
}

// A decimal string as a number for scaling bars only; every figure printed is the server's own
export const num = (value: string | number | null | undefined): number => toAmount(value ?? 0);

// "4.2" → "4,2"
export const decimal = (value: string | number): string => String(value).replace('.', ',');

// "1 250 000" → a short axis label: "1,3 mln" / "250 ming"
export function shortMoney(value: number): string {
  const abs = Math.abs(value);
  const sign = value < 0 ? '−' : '';
  if (abs >= 1_000_000_000) return `${sign}${decimal((abs / 1_000_000_000).toFixed(1).replace(/\.0$/, ''))} mlrd`;
  if (abs >= 1_000_000) return `${sign}${decimal((abs / 1_000_000).toFixed(1).replace(/\.0$/, ''))} mln`;
  if (abs >= 1_000) return `${sign}${Math.round(abs / 1_000)} ming`;
  return `${sign}${Math.round(abs)}`;
}
