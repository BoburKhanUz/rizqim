import { cx } from './cx';

// A spinning ring in the current text colour. Decorative: the control or region around it says what is loading.
export function Spinner({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cx('inline-block size-5 shrink-0 rounded-full border-2 border-current border-t-transparent animate-spin', className)}
    />
  );
}
