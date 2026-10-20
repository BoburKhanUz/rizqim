import { forwardRef, type ButtonHTMLAttributes } from 'react';
import type { LucideIcon } from 'lucide-react';
import { buttonClasses, type ButtonSize, type ButtonVariant } from './buttonStyles';
import { Spinner } from './Spinner';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  fullWidth?: boolean;
  // Busy: the button is disabled and shows a spinner in place of its icon; the label stays (no layout jump)
  loading?: boolean;
  icon?: LucideIcon;
  iconRight?: LucideIcon;
}

// The one button of the app (docs/DESIGN.md, "Komponentlar"). primary: the main action of a screen or card;
// secondary: the other actions; ghost: low-emphasis (links, "Yopish"); danger: destructive; success: a positive
// state change (e.g. "Yetkazib berdim").
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', fullWidth = false, loading = false, icon: Icon, iconRight: IconRight, className, disabled, children, type = 'button', ...rest },
  ref,
) {
  const iconSize = size === 'lg' ? 'size-5' : 'size-[18px]';
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={buttonClasses({ variant, size, fullWidth, className })}
      {...rest}
    >
      {loading ? <Spinner className={iconSize} /> : Icon && <Icon aria-hidden="true" className={iconSize} />}
      {children}
      {IconRight && !loading && <IconRight aria-hidden="true" className={iconSize} />}
    </button>
  );
});
