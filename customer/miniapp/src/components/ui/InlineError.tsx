import { InlineError as BaseInlineError } from '@rizqim/ui';
import { useLanguageStore } from '../../store/languageStore';

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
// The look is @rizqim/ui's; this binds the Mini App's language.
export function InlineError({ title, message, ...rest }: InlineErrorProps) {
  const { t } = useLanguageStore();
  return <BaseInlineError title={title ?? t('loadErrorTitle')} message={message ?? t('loadErrorText')} retryLabel={t('retry')} {...rest} />;
}
