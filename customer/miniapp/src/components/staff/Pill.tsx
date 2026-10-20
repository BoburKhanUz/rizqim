import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cx } from '@rizqim/ui';

export type PillTone = 'success' | 'warning' | 'danger' | 'info' | 'brand' | 'neutral';

const TONE: Record<PillTone, string> = {
  success: 'bg-success-soft text-success-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
  brand: 'bg-brand-soft text-brand-soft-fg',
  neutral: 'bg-surface-2 text-fg-muted',
};

// A small status label (not an order status: that is <StatusBadge>): soft colour + optional icon + a word.
export function Pill({ tone = 'neutral', icon: Icon, children, className }: { tone?: PillTone; icon?: LucideIcon; children: ReactNode; className?: string }) {
  return (
    <span className={cx('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-caption font-bold', TONE[tone], className)}>
      {Icon && <Icon aria-hidden="true" className="size-3.5 shrink-0" />}
      {children}
    </span>
  );
}
