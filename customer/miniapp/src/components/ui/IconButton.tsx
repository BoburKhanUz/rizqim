import { forwardRef, type ButtonHTMLAttributes } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cx } from './cx';

type IconButtonVariant = 'plain' | 'surface' | 'brand' | 'danger';

const VARIANT: Record<IconButtonVariant, string> = {
  plain: 'text-fg-muted hover:bg-surface-2',
  surface: 'bg-surface text-fg border border-line',
  brand: 'bg-brand-soft text-brand-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
};

export interface IconButtonProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'aria-label' | 'children'> {
  icon: LucideIcon;
  // Required: an icon alone says nothing to a screen reader
  label: string;
  variant?: IconButtonVariant;
}

// A button that is only an icon, always with a 44x44 hit area (the drawn circle may be smaller, the target is not).
export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
  { icon: Icon, label, variant = 'plain', className, type = 'button', ...rest },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      aria-label={label}
      title={label}
      className={cx(
        'inline-flex size-11 shrink-0 items-center justify-center rounded-full cursor-pointer transition-[transform,background-color] motion-safe:active:scale-95',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50',
        VARIANT[variant],
        className,
      )}
      {...rest}
    >
      <Icon aria-hidden="true" className="size-5" />
    </button>
  );
});
