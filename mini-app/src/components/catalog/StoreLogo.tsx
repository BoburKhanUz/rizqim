import { realImageUrl } from '../../lib/image';
import { cx } from '../ui';

interface StoreLogoProps {
  name: string;
  logoUrl?: string | null;
  // Size and shape, e.g. "size-12 rounded-card"
  className?: string;
}

// The logo of a store, or its initials on the soft brand colour when it has none.
export function StoreLogo({ name, logoUrl, className }: StoreLogoProps) {
  const url = realImageUrl(logoUrl);
  if (url) return <img src={url} alt="" loading="lazy" className={cx('shrink-0 bg-surface-2 object-cover', className)} />;
  const initials = name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
  return (
    <span aria-hidden="true" className={cx('flex shrink-0 items-center justify-center bg-brand-soft text-body font-bold text-brand-soft-fg', className)}>
      {initials}
    </span>
  );
}
