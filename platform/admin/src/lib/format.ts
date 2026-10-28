import { formatDateTime } from '@rizqim/ui';

// "27-okt, 14:05" for a timestamp the backend sent; `fallback` for nothing
export function when(value: string | null | undefined, fallback = '—'): string {
  return value ? formatDateTime(value, 'uz') : fallback;
}

// "4:59": a countdown in minutes and seconds
export function clock(totalSeconds: number): string {
  const seconds = Math.max(0, Math.round(totalSeconds));
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
