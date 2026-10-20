import { WifiOff, RotateCw } from 'lucide-react';
import { Button } from './Button';
import { cx } from './cx';

interface InlineErrorProps {
  // What failed, in the user's language
  title: string;
  message: string;
  // Shown as a "Try again" button; without `onRetry` there is no button
  onRetry?: () => void;
  // The label of the "Try again" button (required with onRetry)
  retryLabel?: string;
  retrying?: boolean;
  className?: string;
}

// A data screen could not load: said in place, inside the page, so the header and the navigation stay usable.
// The texts come from the app (this package has no language of its own).
export function InlineError({ title, message, onRetry, retryLabel, retrying = false, className }: InlineErrorProps) {
  return (
    <div role="alert" className={cx('flex flex-col items-center gap-3 rounded-card bg-surface px-6 py-10 text-center', className)}>
      <span className="flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-soft-fg">
        <WifiOff aria-hidden="true" className="size-6" />
      </span>
      <h2 className="text-headline font-bold text-fg">{title}</h2>
      <p className="max-w-xs text-footnote text-fg-muted">{message}</p>
      {onRetry && (
        <Button variant="secondary" icon={RotateCw} loading={retrying} onClick={onRetry} className="mt-1">
          {retryLabel}
        </Button>
      )}
    </div>
  );
}
