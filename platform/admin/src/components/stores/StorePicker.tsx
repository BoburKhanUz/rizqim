'use client';

import { useEffect, useId, useState } from 'react';
import { Search, X } from 'lucide-react';
import { cx, Field, fieldClass, IconButton } from '@rizqim/ui';
import { useLookup } from '@/lib/api/stores';
import { useT } from '../I18nProvider';

// P4-22: the store and region pickers (GET /admin/stores/lookup, admin.stores.view or admin.orders.view). They replace the
// raw id fields of the Buyurtmalar filters and choose stores for an announcement. The store picker is a combobox: type a
// name, pick one of up to 20 matches with the mouse or the arrow keys and Enter; a chosen store shows its name (looked up by
// id when the choice came from the address bar).

export function StorePicker({
  value,
  onChange,
  label,
  placeholder,
  className,
}: {
  value: number | null;
  onChange: (id: number | null, name: string | null) => void;
  label?: string;
  placeholder?: string;
  className?: string;
}) {
  const t = useT();
  const listId = useId();
  const [text, setText] = useState('');
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);

  useEffect(() => {
    const timer = setTimeout(() => setQuery(text), 250);
    return () => clearTimeout(timer);
  }, [text]);

  const chosen = useLookup('', value, value !== null);
  const found = useLookup(query, null, open);
  const options = found.data?.stores ?? [];
  const chosenName = value !== null ? (chosen.data?.stores.find((store) => store.id === value)?.name ?? `#${value}`) : null;

  const pick = (id: number, name: string) => {
    onChange(id, name);
    setOpen(false);
    setText('');
  };

  if (value !== null) {
    return (
      <Field label={label ?? t('stores_pickerStore')} className={className}>
        {(control) => (
          <div className="flex items-center gap-1">
            <input {...control} readOnly className={cx(fieldClass, 'flex-1')} value={chosenName ?? ''} />
            <IconButton icon={X} label={t('stores_pickerClear')} onClick={() => onChange(null, null)} />
          </div>
        )}
      </Field>
    );
  }

  return (
    <Field label={label ?? t('stores_pickerStore')} className={className}>
      {(control) => (
        <div className="relative">
          <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
          <input
            {...control}
            type="text"
            role="combobox"
            aria-expanded={open}
            aria-controls={listId}
            aria-autocomplete="list"
            aria-activedescendant={open && options[active] ? `${listId}-${options[active].id}` : undefined}
            autoComplete="off"
            className={cx(fieldClass, 'pl-9')}
            placeholder={placeholder ?? t('stores_pickerSearch')}
            value={text}
            onFocus={() => setOpen(true)}
            onBlur={() => setTimeout(() => setOpen(false), 150)}
            onChange={(event) => {
              setText(event.target.value);
              setActive(0);
              setOpen(true);
            }}
            onKeyDown={(event) => {
              if (event.key === 'ArrowDown') {
                event.preventDefault();
                setOpen(true);
                setActive((index) => Math.min(index + 1, Math.max(options.length - 1, 0)));
              } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((index) => Math.max(index - 1, 0));
              } else if (event.key === 'Enter' && open && options[active]) {
                event.preventDefault();
                pick(options[active].id, options[active].name);
              } else if (event.key === 'Escape') {
                setOpen(false);
              }
            }}
          />
          {open && (
            <ul id={listId} role="listbox" aria-label={t('stores_pickerOptions')} className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-control border border-line bg-surface py-1 shadow-lg">
              {options.length === 0 ? (
                <li className="px-3 py-2 text-footnote text-fg-muted">{found.isFetching ? t('loading') : t('stores_pickerNone')}</li>
              ) : (
                options.map((store, index) => (
                  <li
                    key={store.id}
                    id={`${listId}-${store.id}`}
                    role="option"
                    aria-selected={index === active}
                    className={cx('flex min-h-11 cursor-pointer flex-col justify-center px-3 py-1.5 text-footnote', index === active ? 'bg-surface-2' : 'hover:bg-surface-2')}
                    onMouseDown={(event) => {
                      event.preventDefault();
                      pick(store.id, store.name);
                    }}
                  >
                    <span className="font-bold text-fg">{store.name}</span>
                    {store.region && <span className="text-caption text-fg-muted">{store.region}</span>}
                  </li>
                ))
              )}
            </ul>
          )}
        </div>
      )}
    </Field>
  );
}

export function RegionSelect({ value, onChange, label, anyLabel, className }: { value: string; onChange: (id: string) => void; label?: string; anyLabel?: string; className?: string }) {
  const t = useT();
  const regions = useLookup('', null, true);
  return (
    <Field label={label ?? t('stores_pickerRegion')} className={className}>
      {(control) => (
        <select {...control} className={fieldClass} value={value} onChange={(event) => onChange(event.target.value)}>
          <option value="">{anyLabel ?? t('stores_anyRegion')}</option>
          {(regions.data?.regions ?? []).map((region) => (
            <option key={region.id} value={String(region.id)}>
              {region.name}
            </option>
          ))}
          {value !== '' && regions.data && !regions.data.regions.some((region) => String(region.id) === value) && <option value={value}>#{value}</option>}
        </select>
      )}
    </Field>
  );
}
