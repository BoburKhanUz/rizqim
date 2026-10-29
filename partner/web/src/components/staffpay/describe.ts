import { formatMoney, type Language } from '@rizqim/ui';
import type { Translate } from '@/i18n';
import type { PayComponent } from './api';

// "5.00" → "5", "2.50" → "2,5" (uz/ru), "2.5" (en)
export function formatPercent(value: string | null | undefined, language: Language): string {
  const trimmed = String(value ?? '0').replace(/\.?0+$/, '') || '0';
  return `${language === 'en' ? trimmed : trimmed.replace('.', ',')}%`;
}

// "1 234 567,50" or "150000" typed by a person → "1234567.50"; null when it is not an amount
export function parseAmountInput(raw: string): string | null {
  const cleaned = raw.replace(/[\s ]/g, '').replace(',', '.');
  return /^-?\d{1,12}(\.\d{1,2})?$/.test(cleaned) ? cleaned : null;
}

// One part of a rule in one line ("150 000 so'm har smena", "Sotuvdan 5%", "Garant 120 000 so'm har smena + 5% sotuvdan")
export function describeComponent(component: PayComponent, t: Translate, language: Language): string {
  const amount = formatMoney(component.amount ?? '0', language);
  const percent = formatPercent(component.percent, language);
  switch (component.type) {
    case 'shift_rate':
      return t('sp_desc_shift', { amount });
    case 'hourly_rate':
      return t('sp_desc_hourly', { amount });
    case 'sales_percent':
      return t('sp_desc_sales', { percent });
    case 'service_charge_percent':
      return t('sp_desc_service', { percent });
    case 'guarantee':
      return t(component.mode === 'plus' ? 'sp_desc_guaranteePlus' : 'sp_desc_guaranteeMax', {
        amount,
        per: t(component.per === 'period' ? 'sp_per_period' : 'sp_per_shift'),
        percent,
        base: t(component.base === 'service_charge' ? 'sp_base_service' : 'sp_base_sales'),
      });
    case 'event_rate':
      return t('sp_desc_event', { amount });
    case 'tips':
      return t('sp_desc_tips');
  }
}

// Minutes as "7 soat 05 daq"
export function formatMinutes(minutes: number, t: Translate): string {
  return t('sp_hoursMinutes', { h: Math.floor(minutes / 60), m: String(minutes % 60).padStart(2, '0') });
}

// An ISO moment as the value of an <input type="datetime-local"> on this device ("2026-11-13T09:05")
export function toLocalInput(iso: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

// The value of a datetime-local input (this device's time) as an ISO moment; null for an empty one
export function fromLocalInput(value: string): string | null {
  if (value === '') return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

// The calendar day on this device, YYYY-MM-DD
export function todayIso(offsetDays = 0): string {
  const now = new Date();
  now.setDate(now.getDate() + offsetDays);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}
