import { useState } from 'react';
import { UtensilsCrossed } from 'lucide-react';
import { realImageUrl } from '../../lib/image';
import { cx } from '@rizqim/ui';

interface ProductImageProps {
  src: string | null | undefined;
  // Size and shape, e.g. "size-20 rounded-control"
  className?: string;
}

// A dish photo, or a neutral placeholder (an icon on the inset colour) when it has none or it does not load.
export function ProductImage({ src, className }: ProductImageProps) {
  const url = realImageUrl(src);
  const [failed, setFailed] = useState<string | null>(null);
  if (url && failed !== url) {
    return <img src={url} alt="" loading="lazy" onError={() => setFailed(url)} className={cx('shrink-0 bg-surface-2 object-cover', className)} />;
  }
  return (
    <span aria-hidden="true" data-testid="product-image-placeholder" className={cx('flex shrink-0 items-center justify-center bg-surface-2 text-fg-subtle', className)}>
      <UtensilsCrossed className="size-1/3 min-h-5 min-w-5" />
    </span>
  );
}
