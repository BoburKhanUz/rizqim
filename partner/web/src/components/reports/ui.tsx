'use client';

import type { ReactNode } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button, cx } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';

// Small pieces the reports and finance screens share.

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const TONE: Record<Tone, string> = {
  success: 'bg-success-soft text-success-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
  neutral: 'bg-surface-2 text-fg-muted',
};

// A state in words (the colour only backs the label up, docs/DESIGN.md)
export function Pill({ tone, children }: { tone: Tone; children: ReactNode }) {
  return <span className={cx('inline-flex min-h-6 items-center rounded-full px-2.5 text-caption font-bold whitespace-nowrap', TONE[tone])}>{children}</span>;
}

// A table that scrolls sideways on a narrow screen instead of squeezing its columns
export function DataTable({ caption, children }: { caption: string; children: ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max border-collapse text-left text-body">
        <caption className="sr-only">{caption}</caption>
        {children}
      </table>
    </div>
  );
}

export function Th({ children, numeric }: { children?: ReactNode; numeric?: boolean }) {
  return (
    <th scope="col" className={cx('px-3 py-2 text-footnote font-bold text-fg-muted', numeric && 'text-right')}>
      {children}
    </th>
  );
}

export function Td({ children, numeric, muted, className }: { children?: ReactNode; numeric?: boolean; muted?: boolean; className?: string }) {
  return <td className={cx('border-t border-line px-3 py-2.5 align-top', numeric && 'text-right tabular-nums', muted ? 'text-fg-muted' : 'text-fg', className)}>{children}</td>;
}

// Laravel page controls: "Sahifa 2 / 5 · Jami: 120" with the previous and next page
export function Pager({ page, lastPage, total, busy, onPage }: { page: number; lastPage: number; total: number; busy?: boolean; onPage: (page: number) => void }) {
  const { t } = useI18n();
  if (lastPage <= 1 && total <= 0) return null;
  return (
    <nav className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3" aria-label={t('fin_pageOf', { page, pages: Math.max(lastPage, 1) })}>
      <p className="text-footnote text-fg-muted">
        {t('fin_pageOf', { page, pages: Math.max(lastPage, 1) })} · {t('fin_total', { n: total })}
      </p>
      <div className="flex gap-2">
        <Button variant="secondary" icon={ChevronLeft} disabled={page <= 1 || busy} onClick={() => onPage(page - 1)}>
          {t('fin_prev')}
        </Button>
        <Button variant="secondary" iconRight={ChevronRight} disabled={page >= lastPage || busy} onClick={() => onPage(page + 1)}>
          {t('fin_next')}
        </Button>
      </div>
    </nav>
  );
}
