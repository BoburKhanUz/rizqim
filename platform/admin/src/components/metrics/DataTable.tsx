import type { ReactNode } from 'react';
import { cx } from '@rizqim/ui';

// A plain table for the numbers behind a chart: scrolls sideways on a phone instead of breaking the page
export function DataTable({ caption, children }: { caption: string; children: ReactNode }) {
  return (
    <div className="-mx-1 overflow-x-auto px-1">
      <table className="w-full min-w-[28rem] border-collapse text-footnote">
        <caption className="sr-only">{caption}</caption>
        {children}
      </table>
    </div>
  );
}

export function Th({ children, numeric = false, row = false }: { children: ReactNode; numeric?: boolean; row?: boolean }) {
  return (
    <th scope={row ? 'row' : 'col'} className={cx('border-b border-line px-2 py-2 font-bold whitespace-nowrap', numeric ? 'text-right' : 'text-left', row ? 'text-fg' : 'text-fg-muted')}>
      {children}
    </th>
  );
}

export function Td({ children, numeric = false, tone }: { children: ReactNode; numeric?: boolean; tone?: 'success' | 'danger' }) {
  return (
    <td className={cx('border-b border-line px-2 py-2 whitespace-nowrap', numeric && 'text-right tabular-nums', tone === 'success' && 'text-success', tone === 'danger' && 'text-danger')}>
      {children}
    </td>
  );
}
