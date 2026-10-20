import { cx } from '../ui/cx';

// The look of a text input / select / textarea of the staff forms: 16px text (iOS does not zoom in), a 44px height.
export const fieldClass = cx(
  'w-full min-h-11 rounded-control border border-line bg-surface-2 px-3 py-2.5 text-base text-fg outline-none',
  'placeholder:text-fg-subtle focus:border-brand disabled:cursor-not-allowed disabled:opacity-60',
);
