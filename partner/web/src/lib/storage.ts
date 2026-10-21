// localStorage for small per-browser preferences (the chosen branch, the sound switch). Storage can be missing or throw
// (private mode, blocked site data, a server render): every access is guarded and a failure means "nothing saved".
export function readStored(key: string): string | null {
  try {
    return typeof window === 'undefined' ? null : window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function writeStored(key: string, value: string | null): void {
  try {
    if (typeof window === 'undefined') return;
    if (value === null) window.localStorage.removeItem(key);
    else window.localStorage.setItem(key, value);
  } catch {
    // not saved: the choice lasts for this page only
  }
}
