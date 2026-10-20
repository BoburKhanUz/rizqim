import { useEffect, useRef } from 'react';

// setInterval for UI timers that must not run while the page is hidden: it stops on `visibilitychange` to
// hidden, starts again (a full period later) when the page is visible, and is cleared on unmount.
// Pass `null` to switch it off. The latest `callback` is always the one that runs.
export function useVisibleInterval(callback: () => void, delayMs: number | null): void {
  const latestCallback = useRef(callback);

  useEffect(() => {
    latestCallback.current = callback;
  });

  useEffect(() => {
    if (delayMs === null) return;

    let timer: ReturnType<typeof setInterval> | undefined;
    const start = () => {
      if (timer === undefined) timer = setInterval(() => latestCallback.current(), delayMs);
    };
    const stop = () => {
      if (timer !== undefined) {
        clearInterval(timer);
        timer = undefined;
      }
    };
    const onVisibilityChange = () => (document.hidden ? stop() : start());

    if (!document.hidden) start();
    document.addEventListener('visibilitychange', onVisibilityChange);
    return () => {
      document.removeEventListener('visibilitychange', onVisibilityChange);
      stop();
    };
  }, [delayMs]);
}
