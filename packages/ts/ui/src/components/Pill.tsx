import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cx } from './cx';

export type PillTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const TONE: Record<PillTone, string> = {
  success: 'bg-success-soft text-success-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
  neutral: 'bg-surface-2 text-fg-muted border border-line',
};

// A small status label (open now, PIN set, active ...). The words come from the screen; the colour pair is a design token
// pair with the contrast the design system guarantees, in light and dark.
export function Pill({ tone = 'neutral', icon: Icon, children, className }: { tone?: PillTone; icon?: LucideIcon; children: ReactNode; className?: string }) {
  return (
    <span className={cx('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-caption font-bold', TONE[tone], className)}>
      {Icon && <Icon aria-hidden="true" className="size-3.5" />}
      {children}
    </span>
  );
}
