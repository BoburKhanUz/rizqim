import { formatMoney } from '@rizqim/ui';
import type { PillTone } from '@rizqim/ui';
import type { MessageKey, Translate } from '@/i18n';
import type { BannerStatus, CodeStatus, CodeType, PromoCode } from '@/lib/api/marketing';

// Words, tones and date helpers of the Marketing screens: a state is always said in words, the colour only repeats it.

export const money = (value: string | number | null | undefined) => formatMoney(value ?? 0, 'uz');

export const TABS = ['banners', 'codes', 'categories'] as const;
export type MarketingTab = (typeof TABS)[number];
export const isTab = (value: string | undefined): value is MarketingTab => TABS.includes(value as MarketingTab);

export const TAB_LABEL: Record<MarketingTab, MessageKey> = {
  banners: 'marketing_tab_banners',
  codes: 'marketing_tab_codes',
  categories: 'marketing_tab_categories',
};

export const BANNER_TONE: Record<BannerStatus, PillTone> = { live: 'success', scheduled: 'info', ended: 'neutral', inactive: 'neutral' };
export const BANNER_LABEL: Record<BannerStatus, MessageKey> = {
  live: 'marketing_banner_live',
  scheduled: 'marketing_banner_scheduled',
  ended: 'marketing_banner_ended',
  inactive: 'marketing_banner_inactive',
};

export const CODE_TONE: Record<CodeStatus, PillTone> = { active: 'success', inactive: 'neutral', expired: 'warning', exhausted: 'warning' };
export const CODE_LABEL: Record<CodeStatus, MessageKey> = {
  active: 'marketing_code_active',
  inactive: 'marketing_code_inactive',
  expired: 'marketing_code_expired',
  exhausted: 'marketing_code_exhausted',
};

// "10 %" or "5 000 so'm"; a percent code with a cap says it
export function discountText(code: Pick<PromoCode, 'type' | 'value' | 'max_discount_amount'>, t: Translate): string {
  if (code.type === 'fixed') return money(code.value);
  const percent = `${Number(code.value)} %`;
  return code.max_discount_amount ? t('marketing_code_percentCap', { percent, cap: money(code.max_discount_amount) }) : percent;
}

export const TYPE_LABEL: Record<CodeType, MessageKey> = { percentage: 'marketing_type_percentage', fixed: 'marketing_type_fixed' };

// --- Time (the control center works in Asia/Tashkent, UTC+5 all year) -------------------------------------------------

const TASHKENT_OFFSET = '+05:00';

// An ISO moment as the value of <input type="datetime-local"> in Tashkent time ("2026-12-01T09:00"); '' for nothing
export function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const shifted = new Date(date.getTime() + 5 * 3_600_000);
  return shifted.toISOString().slice(0, 16);
}

// The value of a datetime-local input (Tashkent time) as ISO 8601 with its offset; null when empty or not a moment
export function fromLocalInput(value: string): string | null {
  const text = value.trim();
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(text)) return null;
  const iso = `${text}:00${TASHKENT_OFFSET}`;
  return Number.isNaN(new Date(iso).getTime()) ? null : iso;
}

// The size cap of an image (the backend's rule: 4 MB) and the types it takes
export const IMAGE_MAX_BYTES = 4 * 1024 * 1024;
export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;
export const imageProblem = (file: File): 'type' | 'size' | null => (!IMAGE_TYPES.includes(file.type as (typeof IMAGE_TYPES)[number]) ? 'type' : file.size > IMAGE_MAX_BYTES ? 'size' : null);

// Moves the item at `index` one place (-1 up, +1 down) in a copy
export function moved<T>(items: readonly T[], index: number, step: -1 | 1): T[] {
  const target = index + step;
  if (target < 0 || target >= items.length) return [...items];
  const next = [...items];
  [next[index], next[target]] = [next[target] as T, next[index] as T];
  return next;
}

export const sameOrder = (a: readonly number[], b: readonly number[]) => a.length === b.length && a.every((id, index) => id === b[index]);
