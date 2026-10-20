import { cx } from './cx';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
export type ButtonSize = 'md' | 'lg';

const VARIANT: Record<ButtonVariant, string> = {
  primary: 'bg-brand text-on-brand',
  secondary: 'bg-surface-2 text-fg border border-line',
  ghost: 'bg-transparent text-brand hover:bg-brand-soft',
  danger: 'bg-danger text-on-danger',
  success: 'bg-success text-on-success',
};

// md: 44px (the minimum touch target), lg: 52px (the one main action of a screen)
const SIZE: Record<ButtonSize, string> = {
  md: 'min-h-11 px-4 text-body gap-2',
  lg: 'min-h-13 px-5 text-headline gap-2.5',
};

// The classes of a Button, for a <Link>/<a> that has to look like one: <Link className={buttonClasses()} />.
export function buttonClasses({
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  className,
}: { variant?: ButtonVariant; size?: ButtonSize; fullWidth?: boolean; className?: string } = {}): string {
  return cx(
    'inline-flex items-center justify-center rounded-card font-bold select-none cursor-pointer',
    'transition-[transform,opacity,background-color] duration-150 motion-safe:active:scale-[0.98]',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
    'disabled:opacity-50 disabled:cursor-not-allowed aria-disabled:opacity-50',
    VARIANT[variant],
    SIZE[size],
    fullWidth && 'w-full',
    className,
  );
}
