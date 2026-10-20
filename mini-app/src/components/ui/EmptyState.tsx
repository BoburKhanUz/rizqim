import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cx } from './cx';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  text?: string;
  // e.g. <Button> or <Link className={buttonClasses()}>: the one thing to do next
  action?: ReactNode;
  className?: string;
}

// Nothing to show yet (no orders, an empty cart, no notifications): what it is, why, and what to do next.
export function EmptyState({ icon: Icon, title, text, action, className }: EmptyStateProps) {
  return (
    <div className={cx('flex flex-col items-center gap-3 px-6 py-12 text-center', className)}>
      <span className="flex size-16 items-center justify-center rounded-full bg-brand-soft text-brand-soft-fg">
        <Icon aria-hidden="true" className="size-8" />
      </span>
      <h2 className="text-headline font-bold text-fg">{title}</h2>
      {text && <p className="max-w-xs text-footnote text-fg-muted">{text}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}
