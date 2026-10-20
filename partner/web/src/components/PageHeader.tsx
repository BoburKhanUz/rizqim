import type { ReactNode } from 'react';

// The top of a page: a title, a line under it, and something on the right (a refresh note, a button)
export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div className="min-w-0">
        <h1 className="text-title font-bold text-fg">{title}</h1>
        {subtitle && <p className="text-footnote text-fg-muted">{subtitle}</p>}
      </div>
      {action}
    </header>
  );
}
