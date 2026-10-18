import { Component, type ErrorInfo, type ReactNode } from 'react';
import { logApiError } from '../lib/apiError';

// Loading Skeleton / Spinner
export const FullScreenLoader = ({ message = 'Connecting...' }) => (
  <div className="fixed inset-0 flex flex-col items-center justify-center bg-bg z-50">
    <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4"></div>
    <p className="text-hint font-medium">{message}</p>
  </div>
);

// Fallback UI for Auth Errors
export const ErrorFallback = ({ error }: { error: string }) => (
  <div className="fixed inset-0 flex flex-col items-center justify-center bg-bg p-6 text-center z-50">
    <div className="text-4xl mb-4">⚠️</div>
    <h2 className="text-xl font-bold text-text mb-2">Authentication Failed</h2>
    <p className="text-hint mb-6">{error}</p>
    <button onClick={() => window.location.reload()} className="bg-primary text-white px-6 py-2 rounded-xl font-medium w-full max-w-xs">
      Try Again
    </button>
  </div>
);

// Shown inside the app shell (the navigation bars stay usable) while a lazily loaded page downloads.
export const RouteFallback = () => (
  <div className="flex items-center justify-center py-24" role="status" aria-label="Yuklanmoqda">
    <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
  </div>
);

// A lazily loaded page could not be downloaded (offline, or a new deploy removed the old file).
const RouteLoadError = () => (
  <div className="flex flex-col items-center justify-center gap-3 px-6 py-24 text-center" role="alert">
    <div className="text-4xl">📶</div>
    <h2 className="text-lg font-black text-text-h">Sahifani yuklab bo'lmadi</h2>
    <p className="text-xs text-hint font-semibold max-w-xs leading-relaxed">
      Internet aloqasini tekshiring va qaytadan urining.
    </p>
    <button
      onClick={() => window.location.reload()}
      className="bg-primary text-white px-6 py-3 rounded-2xl font-black text-xs w-full max-w-xs active:scale-95 transition-transform"
    >
      Qayta yuklash
    </button>
  </div>
);

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
