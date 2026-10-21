'use client';

import { Card, Section, cx, formatMoney, formatNumber } from '@rizqim/ui';
import { formatQuantityNumber, type TopProductsReport, type TopProductsSort } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { DataTable, Td, Th } from './ui';

const SORTS: readonly TopProductsSort[] = ['quantity', 'revenue'];

// The best sellers table, in the server's order. The two buttons only change what the server is asked to sort by.
export function TopProducts({ report, sort, onSort }: { report: TopProductsReport | undefined; sort: TopProductsSort; onSort: (sort: TopProductsSort) => void }) {
  const { t, language } = useI18n();
  const products = report?.products ?? [];
  return (
    <Section
      title={t('report_top')}
      action={
        <div role="group" aria-label={t('report_top')} className="flex gap-1 rounded-control bg-surface-2 p-1">
          {SORTS.map((option) => (
            <button
              key={option}
              type="button"
              aria-pressed={sort === option}
              onClick={() => onSort(option)}
              className={cx(
                'min-h-9 cursor-pointer rounded-control px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-brand',
                sort === option ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted',
              )}
            >
              {option === 'quantity' ? t('report_sortQuantity') : t('report_sortRevenue')}
            </button>
          ))}
        </div>
      }
    >
      <Card padded={false} className="px-1 py-2">
        {products.length === 0 ? (
          <p className="px-4 py-3 text-footnote text-fg-muted">{t('report_topEmpty')}</p>
        ) : (
          <DataTable caption={t('report_top')}>
            <thead>
              <tr>
                <Th>#</Th>
                <Th>{t('report_colProduct')}</Th>
                <Th numeric>{t('report_colQuantity')}</Th>
                <Th numeric>{t('report_colOrderCount')}</Th>
                <Th numeric>{t('report_colRevenue')}</Th>
              </tr>
            </thead>
            <tbody>
              {products.map((product, index) => (
                <tr key={`${product.name}-${index}`}>
                  <Td muted>{index + 1}</Td>
                  <Td className="max-w-72 min-w-40 break-words">{product.name}</Td>
                  <Td numeric>{formatQuantityNumber(product.quantity, language)}</Td>
                  <Td numeric>{formatNumber(product.orders, language)}</Td>
                  <Td numeric>{formatMoney(product.revenue, language)}</Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </Card>
    </Section>
  );
}
