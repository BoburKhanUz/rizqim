import { cx } from './cx';

// The look of every text input, select and textarea: 17px text (iOS zooms the page into anything under 16px), at
// least 44px tall, the placeholder in fg-subtle, the brand colour on focus.
export const fieldClass = cx(
  'min-h-11 w-full min-w-0 rounded-control border border-line bg-surface-2 px-3 py-2.5 text-headline text-fg outline-none transition-colors',
  'placeholder:text-fg-subtle focus:border-brand disabled:cursor-not-allowed disabled:opacity-60',
);
