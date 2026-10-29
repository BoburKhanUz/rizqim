'use client';

import { useId, useState, type ReactNode } from 'react';
import { Card, cx } from '@rizqim/ui';
import { useT } from '../I18nProvider';
import { DataTable, Td, Th } from './DataTable';

export interface BarPoint {
  key: string;
  // Under the bar
  short: string;
  value: number;
  // The sentence read out for the bar (hover, focus, screen reader)
  read: string;
  // A month in progress: drawn paler
  partial?: boolean;
}

// One series as bars, plain HTML and CSS like the partner web's revenue chart (docs: no charting library for one series
// and one axis): the bar colour is the brand token, a height is the value over the largest value shown (a display scale
// only: every figure printed comes from the server), and the same numbers are one click away as a table. A bar is a button:
// hover or focus reads it out.
export function BarChart({
  title,
  text,
  points,
  top,
  tableHeaders,
  tableRows,
  footer,
  headerExtra,
}: {
  title: string;
  text?: string;
  points: readonly BarPoint[];
  // The largest value, printed above the plot
  top: string;
  tableHeaders: readonly string[];
  tableRows: readonly (readonly ReactNode[])[];
  footer?: ReactNode;
  headerExtra?: ReactNode;
}) {
  const t = useT();
  const [view, setView] = useState<'chart' | 'table'>('chart');
  const [active, setActive] = useState<number | null>(null);
  const titleId = useId();
  const max = Math.max(0, ...points.map((point) => point.value));
  const selected = active !== null ? points[active] : undefined;
  // About five labels under the bars, whatever the number of bars
  const step = Math.max(1, Math.ceil(points.length / 5));

  return (
    <Card className="flex flex-col gap-3" aria-labelledby={titleId}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <h2 id={titleId} className="text-headline font-bold text-fg">
            {title}
          </h2>
          {text && <p className="max-w-prose text-footnote text-fg-muted">{text}</p>}
        </div>
        <ViewToggle label={title} view={view} onChange={setView} />
      </div>
      {headerExtra}

      {view === 'table' ? (
        <DataTable caption={title}>
          <thead>
            <tr>
              {tableHeaders.map((header, index) => (
                <Th key={header} numeric={index > 0}>
                  {header}
                </Th>
              ))}
            </tr>
          </thead>
          <tbody>
            {tableRows.map((row, rowIndex) => (
              <tr key={points[rowIndex]?.key ?? rowIndex}>
                {row.map((cell, index) => (index === 0 ? <Th key={index} row>{cell}</Th> : <Td key={index} numeric>{cell}</Td>))}
              </tr>
            ))}
          </tbody>
        </DataTable>
      ) : (
        <>
          <p className="min-h-5 text-footnote text-fg-muted" aria-live="polite">
            {selected ? selected.read : t('metrics_chartHint')}
          </p>
          <div className="flex flex-col gap-1" role="group" aria-label={t('metrics_chartAria', { title, count: points.length })}>
            <p className="text-caption text-fg-muted">{top}</p>
            <div className="relative h-44 border-b border-line" onMouseLeave={() => setActive(null)}>
              <div aria-hidden="true" className="absolute inset-x-0 top-0 border-t border-dashed border-line" />
              <div aria-hidden="true" className="absolute inset-x-0 top-1/2 border-t border-dashed border-line" />
              <div className={cx('relative flex h-full items-stretch', points.length > 18 ? 'gap-px' : 'gap-1')}>
                {points.map((point, index) => {
                  const height = max > 0 && point.value > 0 ? Math.max((point.value / max) * 100, 1.5) : 0;
                  return (
                    <button
                      key={point.key}
                      type="button"
                      aria-label={point.read}
                      onMouseEnter={() => setActive(index)}
                      onFocus={() => setActive(index)}
                      onBlur={() => setActive(null)}
                      className="group flex min-w-0 flex-1 cursor-pointer items-end focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
                    >
                      <span style={{ height: `${height}%` }} className={cx('w-full rounded-t-[4px] bg-brand transition-opacity', point.partial && 'opacity-55', active !== null && active !== index && 'opacity-40')} />
                    </button>
                  );
                })}
              </div>
            </div>
            <div aria-hidden="true" className="relative h-5">
              {points.map((point, index) =>
                index % step === 0 ? (
                  <span
                    key={point.key}
                    style={{ left: `${((index + 0.5) / points.length) * 100}%` }}
                    className={cx('absolute top-1 -translate-x-1/2 text-caption whitespace-nowrap text-fg-muted', index === 0 && 'translate-x-0 [left:0!important]', (index / step) % 2 === 1 && 'max-sm:hidden')}
                  >
                    {point.short}
                  </span>
                ) : null,
              )}
            </div>
          </div>
        </>
      )}
      {footer}
    </Card>
  );
}

export function ViewToggle({ label, view, onChange }: { label: string; view: 'chart' | 'table'; onChange: (view: 'chart' | 'table') => void }) {
  const t = useT();
  return (
    <div role="group" aria-label={label} className="flex shrink-0 gap-1 rounded-control bg-surface-2 p-1">
      {(['chart', 'table'] as const).map((mode) => (
        <button
          key={mode}
          type="button"
          aria-pressed={view === mode}
          onClick={() => onChange(mode)}
          className={cx(
            'min-h-9 min-w-20 cursor-pointer rounded-control px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-brand',
            view === mode ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted',
          )}
        >
          {mode === 'chart' ? t('metrics_viewChart') : t('metrics_viewTable')}
        </button>
      ))}
    </div>
  );
}
