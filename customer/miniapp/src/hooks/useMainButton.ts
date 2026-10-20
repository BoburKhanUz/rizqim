import { useEffect, useId, useRef } from 'react';
import { create } from 'zustand';
import { nativeMainButton, resolveCssColor, useThemeStore } from '../lib/twa';

export interface MainButtonOptions {
  text: string;
  // May return a promise: further presses are ignored until it settles
  onClick: () => void | Promise<unknown>;
  enabled?: boolean; // false: shown, but cannot be pressed (e.g. the form is not complete)
  progress?: boolean; // a request is running: a spinner, and it cannot be pressed
  visible?: boolean; // false: hidden (e.g. nothing in the cart); the owner below it, if any, shows instead
}

interface Owner {
  id: string;
  text: string;
  enabled: boolean;
  progress: boolean;
  visible: boolean;
  press: () => void;
}

// The fallback button, when Telegram's MainButton is not there (a browser, the e2e tests). MainButtonHost (in the
// bottom dock) draws it.
interface FallbackState {
  owner: string | null;
  text: string;
  enabled: boolean;
  progress: boolean;
  visible: boolean;
  onClick: () => void;
}

const NO_FALLBACK: FallbackState = { owner: null, text: '', enabled: true, progress: false, visible: false, onClick: () => {} };

export const useMainButtonFallback = create<FallbackState>(() => NO_FALLBACK);

/* There is one MainButton, and more than one screen part can want it: a page ("Buyurtma berish") and a sheet opened
 * over it ("Tasdiqlash"). The owners form a stack in the order they mounted: the most recent visible one is drawn,
 * and when it hides or unmounts the one below it is back, with its own text and action. */
let owners: Owner[] = [];
let nativeOff: (() => void) | null = null;

const activeOwner = (): Owner | null => {
  for (let i = owners.length - 1; i >= 0; i--) if (owners[i].visible) return owners[i];
  return null;
};

function render(): void {
  const active = activeOwner();
  const native = nativeMainButton();
  if (native) {
    if (!active) {
      nativeOff?.();
      nativeOff = null;
      native.hide();
    } else {
      // One handler for the button's whole life: it always presses whoever is on top at that moment
      if (!nativeOff) nativeOff = native.onClick(() => activeOwner()?.press());
      native.update({
        text: active.text,
        enabled: active.enabled,
        progress: active.progress,
        visible: true,
        color: resolveCssColor('var(--rz-brand)'),
        textColor: resolveCssColor('var(--rz-on-brand)'),
      });
    }
    if (useMainButtonFallback.getState().owner !== null) useMainButtonFallback.setState(NO_FALLBACK);
    return;
  }
  useMainButtonFallback.setState(
    active
      ? { owner: active.id, text: active.text, enabled: active.enabled, progress: active.progress, visible: true, onClick: active.press }
      : NO_FALLBACK,
  );
}

// Tests only: forget every owner (a test that does not unmount leaves its owners behind)
export function resetMainButtonForTests(): void {
  owners = [];
  nativeOff?.();
  nativeOff = null;
  useMainButtonFallback.setState(NO_FALLBACK);
}

// A second press of the same action that arrives before the first one disabled the button (two quick taps, two
// events from Telegram) is dropped. A new action (the text changed, e.g. the courier's next step) is pressed at once,
// and so is the same action once the first press's promise has settled (a retry after a refused order).
const REPEAT_GUARD_MS = 700;

// The one main action of a screen ("Buyurtma berish", "Saqlash"), at the bottom, always in reach.
// Inside Telegram it is Telegram's MainButton (painted in the brand colour); elsewhere an in-page button in the
// bottom dock. Returns whether Telegram's button is used. A sheet that asks for it while its page has it takes it
// over, and the page gets it back when the sheet closes.
export function useMainButton({ text, onClick, enabled = true, progress = false, visible = true }: MainButtonOptions): { native: boolean } {
  const id = useId();
  const isNative = nativeMainButton() !== null;
  const themeRevision = useThemeStore((state) => state.revision);

  const latest = useRef({ text, onClick, enabled, progress });
  useEffect(() => {
    latest.current = { text, onClick, enabled, progress };
  });

  // Mount: join the stack on top; unmount: leave it (whoever is below is drawn again)
  useEffect(() => {
    let lastPress = { text: '', at: -Infinity };
    let pending = false;
    const press = () => {
      const { text: action, onClick: handler, enabled: canPress, progress: running } = latest.current;
      const now = Date.now();
      if (!canPress || running || pending) return;
      if (action === lastPress.text && now - lastPress.at < REPEAT_GUARD_MS) return;
      lastPress = { text: action, at: now };
      const result = handler();
      if (result && typeof (result as Promise<unknown>).finally === 'function') {
        pending = true;
        void (result as Promise<unknown>).finally(() => {
          pending = false;
          lastPress = { text: '', at: -Infinity };
        });
      }
    };
    owners = [...owners, { id, text: '', enabled: false, progress: false, visible: false, press }];
    return () => {
      owners = owners.filter((owner) => owner.id !== id);
      render();
    };
  }, [id]);

  // themeRevision: the brand colour differs per theme, read it again when the theme changes
  useEffect(() => {
    owners = owners.map((owner) => (owner.id === id ? { ...owner, text, enabled, progress, visible } : owner));
    render();
  }, [id, text, enabled, progress, visible, isNative, themeRevision]);

  return { native: isNative };
}
