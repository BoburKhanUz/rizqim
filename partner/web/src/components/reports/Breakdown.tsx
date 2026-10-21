'use client';

import { Card, Section, formatMoney, formatNumber, toAmount } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';

export interface BreakdownRow {
  key: string;
  label: string;
  orders: number;
  revenue: string | number;
}

// One breakdown of the sales (by channel, branch, payment method, order type): the name, the server's order count and
// revenue, and a bar for the revenue's share of the largest row (a display scale only).
export function Breakdown({ title, rows }: { title: string; rows: readonly BreakdownRow[] }) {
  const { t, language } = useI18n();
  const max = Math.max(0, ...rows.map((row) => toAmount(row.revenue)));
  return (
    <Section title={title}>
      <Card className="py-2">
        {rows.length === 0 ? (
          <p className="py-3 text-footnote text-fg-muted">—</p>
        ) : (
          <ul className="divide-y divide-line">
            {rows.map((row) => {
              const share = max > 0 ? (toAmount(row.revenue) / max) * 100 : 0;
              return (
                <li key={row.key} className="flex flex-col gap-1.5 py-2.5">
                  <div className="flex items-baseline justify-between gap-3">
                    <span className="min-w-0 truncate text-body text-fg">{row.label}</span>
                    <span className="flex shrink-0 gap-1.5 text-body font-medium text-fg">
                      <span className="text-fg-muted">{t('report_rowOrders', { n: formatNumber(row.orders, language) })}</span>
                      <span aria-hidden="true">·</span>
                      <span>{formatMoney(row.revenue, language)}</span>
                    </span>
                  </div>
                  <div aria-hidden="true" className="h-1.5 rounded-full bg-surface-2">
                    <div className="h-full rounded-full bg-brand" style={{ width: `${share}%` }} />
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </Card>
    </Section>
  );
}
