import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Clock, LockKeyhole, RotateCw, WifiOff, X } from 'lucide-react';
import { logApiError } from '../lib/apiError';
import { tg } from '../lib/telegram';
import { useLanguageStore } from '../store/languageStore';
import { Button, Spinner } from '@rizqim/ui';

// The whole screen while the app starts (signing in). Inside a page use a skeleton or RouteFallback instead.
export const FullScreenLoader = ({ message }: { message?: string }) => {
  const { t } = useLanguageStore();
  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-page p-inset-screen" role="status">
      <Spinner className="size-10 border-4 text-brand" />
      <p className="text-body font-medium text-fg-muted">{message ?? t('loading')}</p>
    </div>
  );
};

// Closes the Mini App (Telegram opens it again with new launch data); outside Telegram, where there is nothing to close,
// a reload
function closeApp() {
  if (typeof tg.close === 'function') tg.close();
  else window.location.reload();
}

// Signing in failed: the app cannot work at all. ONLY for that: a screen whose data failed to load shows
// <InlineError onRetry={refetch} /> inside the page, so the navigation stays.
// `reopen`: this launch's Telegram data is too old to sign in with (lib/launch.ts). A reload keeps the same data and
// would fail again, so the screen says so and offers closing the app to open it again from Telegram; otherwise (a
// transient failure while the data is fresh) the reload retry.
export const ErrorFallback = ({ error, reopen = false }: { error?: string | null; reopen?: boolean }) => {
  const { t } = useLanguageStore();
  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-page p-inset-screen text-center" role="alert">
      <span className="flex size-14 items-center justify-center rounded-full bg-danger-soft text-danger-soft-fg">
        {reopen ? <Clock aria-hidden="true" className="size-7" /> : <LockKeyhole aria-hidden="true" className="size-7" />}
      </span>
      <h2 className="text-title font-bold text-fg">{t(reopen ? 'authExpiredTitle' : 'authErrorTitle')}</h2>
      <p className="max-w-xs text-body text-fg-muted">{t(reopen ? 'authExpiredText' : 'authErrorText')}</p>
      {error && <p className="max-w-xs text-footnote text-fg-muted">{error}</p>}
      {reopen ? (
        <Button icon={X} className="mt-3 w-full max-w-xs" onClick={closeApp}>
          {t('closeApp')}
        </Button>
      ) : (
        <Button icon={RotateCw} className="mt-3 w-full max-w-xs" onClick={() => window.location.reload()}>
          {t('retry')}
        </Button>
      )}
    </div>
  );
};

// Shown inside the app shell (the navigation bars stay usable) while a lazily loaded page downloads.
export const RouteFallback = () => {
  const { t } = useLanguageStore();
  return (
    <div className="flex items-center justify-center py-24" role="status" aria-label={t('loading')}>
      <Spinner className="size-8 border-4 text-brand" />
    </div>
  );
};

// A lazily loaded page could not be downloaded (offline, or a new deploy removed the old file).
const RouteLoadError = () => {
  const { t } = useLanguageStore();
  return (
    <div className="flex flex-col items-center justify-center gap-3 px-6 py-24 text-center" role="alert">
      <span className="flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-soft-fg">
        <WifiOff aria-hidden="true" className="size-6" />
      </span>
      <h2 className="text-headline font-bold text-fg">{t('pageLoadErrorTitle')}</h2>
      <p className="max-w-xs text-footnote text-fg-muted">{t('loadErrorText')}</p>
      <Button icon={RotateCw} className="w-full max-w-xs" onClick={() => window.location.reload()}>
        {t('reload')}
      </Button>
    </div>
  );
};

interface RouteErrorBoundaryProps {
  children: ReactNode;
  // Changing it (e.g. the pathname) clears a previous error so the next page gets a fresh try.
  resetKey?: string;
}

// Catches render errors of the page below it, in particular a failed lazy import, so one broken page does not
// blank the whole app.
export class RouteErrorBoundary extends Component<RouteErrorBoundaryProps, { failed: boolean }> {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    logApiError('Page failed to render:', error);
    if (info.componentStack) console.error(info.componentStack);
  }

  componentDidUpdate(prevProps: RouteErrorBoundaryProps) {
    if (this.state.failed && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ failed: false });
    }
  }

  render() {
    return this.state.failed ? <RouteLoadError /> : this.props.children;
  }
}
