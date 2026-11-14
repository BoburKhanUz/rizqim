'use client';

import Link from 'next/link';
import type { ReactNode } from 'react';
import { CalendarHeart } from 'lucide-react';
import { EmptyState } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useI18n } from '../I18nProvider';
import type { Warning } from './api';

export const EVENT_UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

type Page = 'production' | 'contract' | 'settlement' | 'schedule';

const PAGES: { page: Page; label: MessageKey }[] = [
  { page: 'production', label: 'bqo_navProduction' },
  { page: 'contract', label: 'bqo_navContract' },
  { page: 'settlement', label: 'bqo_navSettlement' },
  { page: 'schedule', label: 'bqo_navSchedule' },
];

// The pages of one event (?event=<uuid>): the kitchen's sheet, the contract, the final settlement and P6-53's schedule.
// Hidden when printing.
export function EventOpsNav({ uuid, current }: { uuid: string; current: Page }) {
  const { t } = useI18n();

  return (
    <nav aria-label={t('bqo_navProduction')} className="mb-4 flex flex-wrap gap-2 print:hidden">
      {PAGES.map(({ page, label }) => (
        <Link
          key={page}
          href={`/banquets/${page}?event=${uuid}`}
          aria-current={page === current ? 'page' : undefined}
          className={
            page === current
              ? 'rounded-control bg-brand px-3 py-1.5 text-footnote font-bold text-on-brand'
              : 'rounded-control border border-line px-3 py-1.5 text-footnote font-bold text-fg hover:bg-surface-muted'
          }
        >
          {t(label)}
        </Link>
      ))}
    </nav>
  );
}

export function NoEvent() {
  const { t } = useI18n();

  return <EmptyState icon={CalendarHeart} title={t('bqo_notFound')} text={t('bqo_notFoundText')} />;
}

export function Warnings({ warnings }: { warnings: Warning[] }) {
  const { t } = useI18n();
  if (warnings.length === 0) return null;

  return (
    <div role="status" className="mb-4 flex flex-col gap-1 rounded-control border border-warning-soft-fg bg-warning-soft p-3 text-footnote text-warning-soft-fg">
      <span className="font-bold">{t('bqo_warnings')}</span>
      {warnings.map((warning) => (
        <span key={warning.code}>{warning.message}</span>
      ))}
    </div>
  );
}

export function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
  return (
    <div className="flex flex-col gap-1 rounded-control border border-line p-3">
      <span className="text-footnote font-bold text-fg-muted">{label}</span>
      <span className="text-headline font-extrabold text-fg">{value}</span>
      {hint && <span className="text-footnote text-fg-muted">{hint}</span>}
    </div>
  );
}

// A quantity of the sheet ("220.000" → "220", "44.500" → "44,5" in the reader's language)
export function formatQuantity(value: string, language: string): string {
  const number = Number(value);
  if (!Number.isFinite(number)) return value;
  return new Intl.NumberFormat(language === 'en' ? 'en-US' : language === 'ru' ? 'ru-RU' : 'uz-UZ', { maximumFractionDigits: 3 }).format(number);
}
