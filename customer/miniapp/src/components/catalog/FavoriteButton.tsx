import { Heart } from 'lucide-react';
import { useLanguageStore } from '../../store/languageStore';
import { cx } from '../ui';

interface FavoriteButtonProps {
  active: boolean;
  onToggle: () => void;
  // On a photo the button gets its own surface so the heart stays visible
  onPhoto?: boolean;
  className?: string;
}

// The heart of a store: a 44x44 toggle.
export function FavoriteButton({ active, onToggle, onPhoto = false, className }: FavoriteButtonProps) {
  const { language } = useLanguageStore();
  const label = language === 'uz'
    ? (active ? 'Sevimlilardan olib tashlash' : "Sevimlilarga qo'shish")
    : (active ? 'Убрать из избранного' : 'В избранное');
  return (
    <button
      type="button"
      aria-pressed={active}
      aria-label={label}
      title={label}
      onClick={onToggle}
      className={cx(
        'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full transition-transform motion-safe:active:scale-90',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
        onPhoto && 'border border-line bg-surface',
        className,
      )}
    >
      <Heart aria-hidden="true" className={cx('size-5', active ? 'fill-danger text-danger' : 'text-fg-muted')} />
    </button>
  );
}
