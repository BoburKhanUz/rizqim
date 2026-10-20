import { UtensilsCrossed } from 'lucide-react';
import { cx } from '../ui';

interface ProductImageProps {
  src: string | null | undefined;
  // Size and shape, e.g. "size-20 rounded-control"
  className?: string;
}

// A dish photo, or a neutral placeholder (an icon on the inset colour) when it has none.
export function ProductImage({ src, className }: ProductImageProps) {
  if (src) return <img src={src} alt="" loading="lazy" className={cx('shrink-0 bg-surface-2 object-cover', className)} />;
  return (
    <span aria-hidden="true" className={cx('flex shrink-0 items-center justify-center bg-surface-2 text-fg-subtle', className)}>
      <UtensilsCrossed className="size-1/3 min-h-5 min-w-5" />
    </span>
  );
}
