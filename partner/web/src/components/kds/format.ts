import type { Translate } from '@/i18n';

// Seconds as the kitchen reads them: 300 → "5:00", 75 → "1:15"; null → "—"
export function formatDuration(seconds: number | null | undefined, t: Translate): string {
  if (seconds === null || seconds === undefined) return '—';
  const whole = Math.max(0, Math.round(seconds));
  return t('kds_duration', { m: Math.floor(whole / 60), s: String(whole % 60).padStart(2, '0') });
}

// A target typed by the owner: whole seconds within the server's limits; '' is "no target" (null); undefined: invalid
export function parseTarget(text: string, limits: { min: number; max: number }): number | null | undefined {
  const value = text.trim();
  if (value === '') return null;
  if (!/^\d+$/.test(value)) return undefined;
  const seconds = Number(value);
  return seconds >= limits.min && seconds <= limits.max ? seconds : undefined;
}

// The server's limits of a station's target (KitchenStationController::MIN_TARGET, MAX_TARGET)
export const TARGET_LIMITS = { min: 10, max: 7200 } as const;
