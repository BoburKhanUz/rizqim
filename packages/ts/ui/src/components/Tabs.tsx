import { cx } from './cx';

// Shared by the tab buttons here and by the apps' own link tabs (partner web: /stoplist and /stoplist/batches)
export const TAB_CLASS =
  'inline-flex min-h-11 items-center rounded-control px-4 text-body font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
export const tabTone = (active: boolean) => (active ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:bg-surface-2');

// Tabs that switch a panel of the same page. The panel is labelled by its tab (`panelId`).
export function Tabs<T extends string>({ tabs, value, onChange, label, idPrefix }: { tabs: ReadonlyArray<{ id: T; label: string }>; value: T; onChange: (id: T) => void; label: string; idPrefix: string }) {
  return (
    <div role="tablist" aria-label={label} className="mb-4 flex flex-wrap gap-2">
      {tabs.map((tab) => (
        <button
          key={tab.id}
          type="button"
          role="tab"
          id={`${idPrefix}-tab-${tab.id}`}
          aria-selected={tab.id === value}
          aria-controls={`${idPrefix}-panel-${tab.id}`}
          onClick={() => onChange(tab.id)}
          className={cx(TAB_CLASS, tabTone(tab.id === value))}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}
