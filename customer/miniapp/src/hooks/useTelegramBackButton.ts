import { useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { hideTelegramBackButton, selectionChanged, showTelegramBackButton } from '../lib/twa';

// Telegram's BackButton (top left of the Mini App header) on every screen except the root ones: it goes back in
// the app's history, or to "/" when the screen was opened directly. Outside Telegram it does nothing.
export function useTelegramBackButton(rootPaths: ReadonlySet<string>): void {
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const isRoot = rootPaths.has(pathname);

  useEffect(() => {
    if (isRoot) {
      hideTelegramBackButton();
      return;
    }
    const remove = showTelegramBackButton(() => {
      selectionChanged();
      // React Router keeps the position in its history in history.state.idx
      if ((window.history.state?.idx ?? 0) > 0) navigate(-1);
      else navigate('/', { replace: true });
    });
    return remove || undefined;
  }, [isRoot, navigate]);
}
