// Focus handling shared by the modal surfaces (Dialog here, the apps' own drawers)

export const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// A keydown handler's part for Tab: keeps the focus inside `panel` (Tab past the last control goes to the first,
// Shift+Tab before the first to the last)
export function keepTabInside(event: KeyboardEvent, panel: HTMLElement): void {
  if (event.key !== 'Tab') return;
  const items = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE));
  const first = items[0];
  const last = items[items.length - 1];
  if (!first || !last) return;
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
