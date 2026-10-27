'use client';

import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { ClipboardList, Search, Store, UserRound, Users, type LucideIcon } from 'lucide-react';
import { cx, keepTabInside, Spinner } from '@rizqim/ui';
import { search } from '@/lib/api/endpoints';
import type { SearchResult } from '@/lib/api/types';
import { errorText } from '@/lib/errors';
import type { MessageKey } from '@/i18n';
import { useT } from './I18nProvider';

const MIN_CHARS = 2;
const DEBOUNCE_MS = 250;

const GROUPS: ReadonlyArray<{ type: string; label: MessageKey; icon: LucideIcon }> = [
  { type: 'store', label: 'search_group_store', icon: Store },
  { type: 'order', label: 'search_group_order', icon: ClipboardList },
  { type: 'user', label: 'search_group_user', icon: UserRound },
  { type: 'member', label: 'search_group_member', icon: Users },
];

// Ctrl+K: one box over GET /admin/search, results grouped by type, up/down/Enter to pick, Esc to close. A result is a path of this
// app (the backend sends it); stage B's pages are the places it leads to.
export function CommandPalette({ onClose }: { onClose: () => void }) {
  const t = useT();
  const router = useRouter();
  const listId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const [text, setText] = useState('');
  const [debounced, setDebounced] = useState('');
  const [active, setActive] = useState(0);
  // The page may pass a new onClose on every render: the focus effect below must not restart for it
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  });

  // The search is sent once the typing pauses
  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(text.trim()), DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [text]);

  const ready = debounced.length >= MIN_CHARS;
  const results = useQuery({
    queryKey: ['admin-search', debounced],
    queryFn: ({ signal }) => search(debounced, signal),
    enabled: ready,
    staleTime: 30_000,
  });

  // Results in the order shown (by group), so the arrow keys walk the list top to bottom
  const groups = useMemo(() => {
    const items = ready ? (results.data ?? []) : [];
    const known = GROUPS.map((group) => ({ ...group, items: items.filter((item) => item.type === group.type) }));
    const other = items.filter((item) => !GROUPS.some((group) => group.type === item.type));
    return [...known, { type: 'other', label: 'search_group_other' as MessageKey, icon: Search, items: other }].filter((group) => group.items.length > 0);
  }, [ready, results.data]);
  const flat = useMemo(() => groups.flatMap((group) => group.items), [groups]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- a new result list starts at its first row
    setActive(0);
  }, [flat]);

  // Focus in, page behind still, Tab stays inside, focus back where it was on close
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    inputRef.current?.focus();
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.stopPropagation();
        onCloseRef.current();
      }
      if (panelRef.current) keepTabInside(event, panelRef.current);
    };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = overflow;
      previous?.focus?.();
    };
  }, []);

  function open(result: SearchResult | undefined) {
    if (!result) return;
    onClose();
    router.push(result.href);
  }

  function onInputKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (flat.length > 0) setActive((index) => (index + 1) % flat.length);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      if (flat.length > 0) setActive((index) => (index - 1 + flat.length) % flat.length);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      open(flat[active]);
    }
  }

  const optionId = (index: number) => `${listId}-option-${index}`;
  let position = -1;

  let status: string | null = null;
  if (!ready) status = t('search_minChars');
  else if (results.isError) status = results.error ? errorText(results.error, t, t('search_error')) : t('search_error');
  else if (results.isSuccess && flat.length === 0) status = t('search_empty');

  return createPortal(
    <div className="fixed inset-0 z-[120] flex items-start justify-center bg-scrim p-3 pt-[10vh] animate-fade-in" onClick={(event) => event.target === event.currentTarget && onClose()}>
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label={t('search_title')}
        className="flex max-h-[80vh] w-full max-w-xl flex-col overflow-hidden rounded-sheet bg-surface text-fg shadow-2xl animate-scale-up"
      >
        <div className="flex items-center gap-2 border-b border-line px-4">
          <Search aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
          <input
            ref={inputRef}
            type="text"
            role="combobox"
            aria-label={t('search_label')}
            aria-expanded={flat.length > 0}
            aria-controls={listId}
            aria-activedescendant={flat.length > 0 ? optionId(active) : undefined}
            aria-autocomplete="list"
            autoComplete="off"
            spellCheck={false}
            placeholder={t('search_placeholder')}
            value={text}
            onChange={(event) => setText(event.target.value)}
            onKeyDown={onInputKeyDown}
            className="min-h-14 min-w-0 flex-1 bg-transparent text-headline text-fg outline-none placeholder:text-fg-subtle"
          />
          {ready && results.isFetching && <Spinner className="size-5" />}
        </div>

        <div className="overflow-y-auto p-2">
          {status && (
            <p role="status" className="px-3 py-6 text-center text-footnote text-fg-muted">
              {status}
            </p>
          )}
          <div id={listId} role="listbox" aria-label={t('search_results')}>
            {groups.map((group) => {
              const Icon = group.icon;
              const headingId = `${listId}-group-${group.type}`;
              return (
                <div key={group.type} role="group" aria-labelledby={headingId} className="py-1">
                  <p id={headingId} className="px-3 py-1 text-caption font-bold uppercase tracking-wide text-fg-muted">
                    {t(group.label)}
                  </p>
                  {group.items.map((item) => {
                    position += 1;
                    const index = position;
                    const selected = index === active;
                    return (
                      <div
                        key={`${item.type}-${item.id}`}
                        id={optionId(index)}
                        role="option"
                        aria-selected={selected}
                        tabIndex={-1}
                        onMouseMove={() => setActive(index)}
                        onClick={() => open(item)}
                        className={cx('flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-3 py-2', selected ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg')}
                      >
                        <Icon aria-hidden="true" className="size-5 shrink-0" />
                        <span className="flex min-w-0 flex-1 flex-col">
                          <span className="truncate text-body font-medium">{item.title}</span>
                          {item.subtitle && <span className={cx('truncate text-footnote', selected ? 'text-brand-soft-fg' : 'text-fg-muted')}>{item.subtitle}</span>}
                        </span>
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}

