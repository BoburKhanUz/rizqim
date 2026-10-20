import { WifiOff, RotateCw } from 'lucide-react';
import { useLanguageStore } from '../../store/languageStore';
import { Button } from './Button';
import { cx } from './cx';

interface InlineErrorProps {
  // What failed, in the user's language; defaults to "Could not load the data"
  title?: string;
  message?: string;
  // Shown as a "Try again" button; without it there is no button
  onRetry?: () => void;
  retrying?: boolean;
  className?: string;
}

// A data screen could not load: said in place, inside the page, so the header and the navigation stay usable.
// Use this (not ErrorFallback) for every failed query; ErrorFallback is only for a failed sign-in.
export function InlineError({ title, message, onRetry, retrying = false, className }: InlineErrorProps) {
  const { t } = useLanguageStore();
  return (
    <div role="alert" className={cx('flex flex-col items-center gap-3 rounded-card bg-surface px-6 py-10 text-center', className)}>
      <span className="flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-soft-fg">
        <WifiOff aria-hidden="true" className="size-6" />
      </span>
      <h2 className="text-headline font-bold text-fg">{title ?? t('loadErrorTitle')}</h2>
      <p className="max-w-xs text-footnote text-fg-muted">{message ?? t('loadErrorText')}</p>
      {onRetry && (
        <Button variant="secondary" icon={RotateCw} loading={retrying} onClick={onRetry} className="mt-1">
          {t('retry')}
        </Button>
      )}
    </div>
  );
}
