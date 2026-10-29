'use client';

import { Card, cx } from '@rizqim/ui';
import { formatQuantity, type MenuUnit } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';

interface WriteOffs {
  count: number;
  items: Array<{ product_id: number; product_name: string | null; unit: string; reason: string; reason_label: string; quantity: number }>;
}

const asUnit = (unit: string): MenuUnit | null => (unit === 'kg' || unit === 'l' || unit === 'piece' ? unit : null);

// The portions written off at the till during the shift (X and Z report, P6-15). The report type of the generated client
// does not know the block until the spec is regenerated, so it is read from the report as it came.
export function readShiftWriteOffs(report: unknown): WriteOffs | null {
  const block = (report as { batch_writeoffs?: WriteOffs } | null | undefined)?.batch_writeoffs;
  return block && block.count > 0 ? block : null;
}

export function ShiftWriteOffs({ writeOffs, className }: { writeOffs: WriteOffs; className?: string }) {
  const { t, language } = useI18n();
  return (
    <Card className={cx('flex flex-col gap-2 break-inside-avoid print:border-fg-subtle print:shadow-none', className)} aria-label={t('dm_zWriteoffs')}>
      <h2 className="text-headline font-bold text-fg">{t('dm_zWriteoffs')}</h2>
      <dl>
        {writeOffs.items.map((item) => (
          <div key={`${item.product_id}-${item.unit}-${item.reason}`} className="flex items-baseline justify-between gap-4 border-b border-line py-1.5 last:border-b-0 print:border-fg-subtle">
            <dt className="text-body text-fg-muted print:text-fg">
              {item.product_name} · {item.reason_label}
            </dt>
            <dd className="text-right text-body font-bold tabular-nums text-fg">{formatQuantity(item.quantity, asUnit(item.unit), language)}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}
