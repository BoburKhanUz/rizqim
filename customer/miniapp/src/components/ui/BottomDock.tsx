import { useLayoutEffect, useRef, type ReactNode } from 'react';
import { cx } from '@rizqim/ui';

// Everything fixed at the bottom of the screen, stacked in one place: the in-page main button, the cart bar, the
// navigation. The dock measures itself and publishes its height as --rz-dock-height, which `pb-dock` (the app shell)
// and `bottom-dock` use, so content and floating elements are never hidden under it, whatever it holds and however
// tall the safe area (iPhone home indicator, Telegram's content inset) makes it.
//
// `withNav`: the last child is a navigation bar that pads itself by the safe area; without one the dock does.
export function BottomDock({ children, withNav }: { children: ReactNode; withNav: boolean }) {
  const ref = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const element = ref.current;
    if (!element) return;
    const root = document.documentElement;
    const publish = () => root.style.setProperty('--rz-dock-height', `${element.offsetHeight}px`);
    publish();
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(publish);
    observer?.observe(element);
    return () => {
      observer?.disconnect();
      root.style.setProperty('--rz-dock-height', '0px');
    };
  }, []);

  return (
    <div
      ref={ref}
      data-testid="bottom-dock"
      className={cx(
        'pointer-events-none fixed inset-x-0 bottom-0 z-40 mx-auto flex max-w-md flex-col [&>*]:pointer-events-auto',
        !withNav && 'pb-[var(--rz-inset-bottom)]',
      )}
    >
      {children}
    </div>
  );
}
