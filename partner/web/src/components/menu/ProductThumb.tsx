import { Utensils } from 'lucide-react';
import { cx } from '@rizqim/ui';

// A product's picture (the small copy if there is one) or a neutral placeholder. Decorative: the name is next to it.
export function ProductThumb({ product, className }: { product: { image_url?: string | null; image_thumb_urls?: { sm: string | null; md: string | null } | null }; className?: string }) {
  const src = product.image_thumb_urls?.sm ?? product.image_url ?? null;
  return (
    <span className={cx('flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-control bg-surface-2 text-fg-muted', className)}>
      {src ? (
        // eslint-disable-next-line @next/next/no-img-element -- the API's own storage URL (S3 or /storage), already resized
        <img src={src} alt="" loading="lazy" className="size-full object-cover" />
      ) : (
        <Utensils aria-hidden="true" className="size-5" />
      )}
    </span>
  );
}
