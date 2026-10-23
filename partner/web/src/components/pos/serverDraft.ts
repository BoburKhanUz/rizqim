import { useState } from 'react';

// A form that edits what the server keeps. The form starts from the data on hand, which can be a cached copy that is being
// read again; when newer data comes, what the person has not touched takes the new values and what they changed stays theirs.
// A list (holidays, weekend days) keeps their additions and removals on top of the newer list.

const same = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b);

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

const order = (a: unknown, b: unknown) => (typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b)));

// `after` with what was added to and removed from `before` in `edited`, sorted
export function rebaseList<T>(edited: readonly T[], before: readonly T[], after: readonly T[]): T[] {
  const removed = before.filter((item) => !edited.includes(item));
  const added = edited.filter((item) => !before.includes(item));
  return [...after.filter((item) => !removed.includes(item)), ...added.filter((item) => !after.includes(item))].sort(order);
}

// The draft moved from the server's `before` onto its `after`: an untouched field takes the new value, a changed one stays
export function rebaseDraft<D>(draft: D, before: D, after: D): D {
  if (same(draft, before)) return after;
  if (Array.isArray(draft) && Array.isArray(before) && Array.isArray(after)) return rebaseList(draft, before, after) as D;
  if (isRecord(draft) && isRecord(before) && isRecord(after)) {
    return Object.fromEntries(Object.keys(draft).map((key) => [key, key in before && key in after ? rebaseDraft(draft[key], before[key], after[key]) : draft[key]])) as D;
  }
  return draft;
}

// The keys of `draft` that differ from `base`
export function changedKeys<D extends object>(draft: D, base: D): Array<keyof D> {
  return (Object.keys(draft) as Array<keyof D>).filter((key) => !same(draft[key], base[key]));
}

// The draft of a form over `server`, kept in step with newer server data. `base` is the server's draft the edits are against.
export function useServerDraft<S, D>(server: S, toDraft: (server: S) => D) {
  const [seed, setSeed] = useState(server);
  const [draft, setDraft] = useState<D>(() => toDraft(server));
  if (server !== seed) {
    // Newer data: rebase during render (React re-renders at once, before showing the stale draft)
    setSeed(server);
    setDraft(rebaseDraft(draft, toDraft(seed), toDraft(server)));
  }
  const base = toDraft(seed);
  return { draft, setDraft, base, dirty: !same(draft, base) };
}
