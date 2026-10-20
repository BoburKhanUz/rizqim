import type { HTMLAttributes, ReactNode } from 'react';
import { cx } from './cx';

// A block of content on the page background: surface colour, 16px radius, a hairline border.
export function Card({ className, padded = true, ...rest }: HTMLAttributes<HTMLDivElement> & { padded?: boolean }) {
  return <div className={cx('rounded-card border border-line bg-surface', padded && 'p-4', className)} {...rest} />;
}

interface SectionProps {
  title?: ReactNode;
  // Something on the right of the title (a "Hammasi" link, a counter)
  action?: ReactNode;
  children: ReactNode;
  className?: string;
  // The accessible name of the region (defaults to the title when it is text)
  label?: string;
}

// A titled group of content on a screen. The title is a caption-sized heading in fg-muted (not shouted, not hint).
export function Section({ title, action, children, className, label }: SectionProps) {
  const name = label ?? (typeof title === 'string' ? title : undefined);
  return (
    <section aria-label={name} className={cx('flex flex-col gap-3', className)}>
      {(title || action) && (
        <div className="flex items-center justify-between gap-3 px-1">
          {title && <h2 className="text-footnote font-bold text-fg-muted">{title}</h2>}
          {action}
        </div>
      )}
      {children}
    </section>
  );
}
