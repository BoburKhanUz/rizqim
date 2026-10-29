'use client';

import { useId, useState } from 'react';
import { Card, cx, formatMoney } from '@rizqim/ui';
import type { MovementMonth } from '@/lib/api/metrics';
import { useT } from '../I18nProvider';
import { ViewToggle } from './BarChart';
import { DataTable, Td, Th } from './DataTable';
import { monthLabel, monthShort, num, shortMoney } from './metricsModel';

const money = (value: string | number) => formatMoney(value, 'uz');

// The four kinds of movement, the colour of each (design tokens: the words in the legend and the table carry the
// meaning, the colour only helps) and the order they stack in: new and expansion above the baseline, contraction and
// churn below it
const PARTS = [
  { key: 'new', label: 'metrics_movementLegendNew', swatch: 'bg-success', up: true },
  { key: 'expansion', label: 'metrics_movementLegendExpansion', swatch: 'bg-info', up: true },
  { key: 'contraction', label: 'metrics_movementLegendContraction', swatch: 'bg-warning', up: false },
  { key: 'churned', label: 'metrics_movementLegendChurned', swatch: 'bg-danger', up: false },
] as const;

// MRR harakati: per month what was added above the baseline (new, expansion) and what was lost below it (contraction,
// churn), scaled to the largest month, with the same numbers as a table (opening, the four parts, net, closing).
export function MovementChart({ months }: { months: readonly MovementMonth[] }) {
  const t = useT();
  const [view, setView] = useState<'chart' | 'table'>('chart');
  const [active, setActive] = useState<number | null>(null);
  const titleId = useId();

  const up = months.map((month) => num(month.new.mrr) + num(month.expansion.mrr));
  const down = months.map((month) => num(month.contraction.mrr) + num(month.churned.mrr));
  const maxUp = Math.max(0, ...up);
  const maxDown = Math.max(0, ...down);
  const total = maxUp + maxDown;
  const read = (month: MovementMonth) =>
    t('metrics_movementRead', { month: monthLabel(month.month), new: money(month.new.mrr), expansion: money(month.expansion.mrr), contraction: money(month.contraction.mrr), churned: money(month.churned.mrr), net: money(month.net_new) });
  const selected = active !== null ? months[active] : undefined;
  const step = Math.max(1, Math.ceil(months.length / 5));

  return (
    <Card className="flex flex-col gap-3" aria-labelledby={titleId}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <h2 id={titleId} className="text-headline font-bold text-fg">
            {t('metrics_movementTitle')}
          </h2>
          <p className="max-w-prose text-footnote text-fg-muted">{t('metrics_movementText')}</p>
        </div>
        <ViewToggle label={t('metrics_movementTitle')} view={view} onChange={setView} />
      </div>

      <ul className="flex flex-wrap gap-x-4 gap-y-1 text-caption text-fg-muted" aria-label={t('metrics_movementTitle')}>
        {PARTS.map((part) => (
          <li key={part.key} className="inline-flex items-center gap-1.5">
            <span aria-hidden="true" className={cx('size-3 rounded-sm', part.swatch)} />
            {t(part.label)}
          </li>
        ))}
      </ul>

      {view === 'table' ? (
        <DataTable caption={t('metrics_movementTitle')}>
          <thead>
            <tr>
              <Th>{t('metrics_colMonth')}</Th>
              <Th numeric>{t('metrics_movementOpening')}</Th>
              {PARTS.map((part) => (
                <Th key={part.key} numeric>
                  {t(part.label)}
                </Th>
              ))}
              <Th numeric>{t('metrics_movementNet')}</Th>
              <Th numeric>{t('metrics_movementClosing')}</Th>
            </tr>
          </thead>
          <tbody>
            {months.map((month) => (
              <tr key={month.month}>
                <Th row>
                  {monthLabel(month.month)}
                  {month.partial && <span className="ml-1 text-caption font-normal text-fg-muted">({t('metrics_partial')})</span>}
                </Th>
                <Td numeric>{money(month.opening)}</Td>
                {PARTS.map((part) => (
                  <Td key={part.key} numeric tone={part.up ? 'success' : 'danger'}>
                    {money(month[part.key].mrr)} <span className="text-caption text-fg-muted">· {t('metrics_stores', { count: month[part.key].stores })}</span>
                  </Td>
                ))}
                <Td numeric>{money(month.net_new)}</Td>
                <Td numeric>{money(month.closing)}</Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      ) : (
        <>
          <p className="min-h-5 text-footnote text-fg-muted" aria-live="polite">
            {selected ? read(selected) : t('metrics_chartHint')}
          </p>
          <div className="flex flex-col gap-1" role="group" aria-label={t('metrics_chartAria', { title: t('metrics_movementTitle'), count: months.length })}>
            <p className="text-caption text-fg-muted">+{shortMoney(maxUp)}</p>
            <div className="relative flex h-52 flex-col" onMouseLeave={() => setActive(null)}>
              <div className="relative flex min-h-0 items-end" style={{ flexGrow: maxUp, flexBasis: 0 }}>
                <Columns months={months} part="up" scale={maxUp} active={active} setActive={setActive} read={read} />
              </div>
              <div aria-hidden="true" className="border-t-2 border-line" />
              <div className="relative flex min-h-0 items-start" style={{ flexGrow: maxDown, flexBasis: 0 }}>
                <Columns months={months} part="down" scale={maxDown} active={active} setActive={setActive} read={read} />
              </div>
              {total === 0 && <div aria-hidden="true" className="flex-1" />}
            </div>
            <p className="text-caption text-fg-muted">−{shortMoney(maxDown)}</p>
            <div aria-hidden="true" className="relative h-5">
              {months.map((month, index) =>
                index % step === 0 ? (
                  <span
                    key={month.month}
                    style={{ left: `${((index + 0.5) / months.length) * 100}%` }}
                    className={cx('absolute top-1 -translate-x-1/2 text-caption whitespace-nowrap text-fg-muted', index === 0 && 'translate-x-0 [left:0!important]', (index / step) % 2 === 1 && 'max-sm:hidden')}
                  >
                    {monthShort(month.month, index)}
                  </span>
                ) : null,
              )}
            </div>
          </div>
        </>
      )}
    </Card>
  );
}

// The columns of one half: every month a button, its parts stacked from the baseline outwards
function Columns({
  months,
  part,
  scale,
  active,
  setActive,
  read,
}: {
  months: readonly MovementMonth[];
  part: 'up' | 'down';
  scale: number;
  active: number | null;
  setActive: (index: number | null) => void;
  read: (month: MovementMonth) => string;
}) {
  const parts = PARTS.filter((item) => item.up === (part === 'up'));
  return (
    <div className={cx('flex h-full w-full', months.length > 18 ? 'gap-px' : 'gap-1')}>
      {months.map((month, index) => (
        <button
          key={month.month}
          type="button"
          // One button per month: only the upper half carries the name, so a screen reader reads each month once
          aria-label={part === 'up' ? read(month) : undefined}
          aria-hidden={part === 'up' ? undefined : true}
          tabIndex={part === 'up' ? 0 : -1}
          onMouseEnter={() => setActive(index)}
          onFocus={() => setActive(index)}
          onBlur={() => setActive(null)}
          className={cx('flex min-w-0 flex-1 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand', part === 'up' ? 'flex-col-reverse justify-start' : 'flex-col justify-start')}
        >
          {parts.map((item) => {
            const value = num(month[item.key].mrr);
            const height = scale > 0 && value > 0 ? Math.max((value / scale) * 100, 1.5) : 0;
            return (
              <span
                key={item.key}
                data-part={item.key}
                style={{ height: `${height}%` }}
                className={cx('w-full', item.swatch, month.partial && 'opacity-60', active !== null && active !== index && 'opacity-35')}
              />
            );
          })}
        </button>
      ))}
    </div>
  );
}
