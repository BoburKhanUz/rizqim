import { useEffect, useId, useRef } from 'react';
import { create } from 'zustand';
import { nativeMainButton, resolveCssColor, useThemeStore } from '../lib/twa';

export interface MainButtonOptions {
  text: string;
  onClick: () => void;
  enabled?: boolean; // false: shown, but cannot be pressed (e.g. the form is not complete)
  progress?: boolean; // a request is running: a spinner, and it cannot be pressed
  visible?: boolean; // false: hidden (e.g. nothing in the cart)
}

// The fallback button, when Telegram's MainButton is not there (a browser, the e2e tests). MainButtonHost (in the
// bottom dock) draws whichever screen registered last.
interface FallbackState {
  owner: string | null;
  text: string;
  enabled: boolean;
  progress: boolean;
  visible: boolean;
  onClick: () => void;
}

export const useMainButtonFallback = create<FallbackState>(() => ({
  owner: null,
  text: '',
  enabled: true,
  progress: false,
  visible: false,
  onClick: () => {},
}));

// The one main action of a screen ("Buyurtma berish", "Saqlash"), at the bottom, always in reach.
// Inside Telegram it is Telegram's MainButton (painted in the brand colour); elsewhere an in-page button in the
// bottom dock. Returns whether Telegram's button is used. One screen at a time: the last one that called it wins,
// and leaving the screen removes it.
export function useMainButton({ text, onClick, enabled = true, progress = false, visible = true }: MainButtonOptions): { native: boolean } {
  const owner = useId();
  const clickRef = useRef(onClick);
  const isNative = nativeMainButton() !== null;
  const themeRevision = useThemeStore((state) => state.revision);

  useEffect(() => {
    clickRef.current = onClick;
  });

  // Telegram's button
  useEffect(() => {
    const native = nativeMainButton();
    if (!native) return;
    native.update({
      text,
      enabled,
      progress,
      visible,
      color: resolveCssColor('var(--rz-brand)'),
      textColor: resolveCssColor('var(--rz-on-brand)'),
    });
    // themeRevision: the brand colour differs per theme, read it again when the theme changes
  }, [isNative, text, enabled, progress, visible, themeRevision]);

  useEffect(() => {
    const native = nativeMainButton();
    if (!native) return;
    const off = native.onClick(() => clickRef.current());
    return () => {
      off();
      native.hide();
    };
  }, [isNative]);

  // The in-page fallback
  useEffect(() => {
    if (isNative) return;
    useMainButtonFallback.setState({ owner, text, enabled, progress, visible, onClick: () => clickRef.current() });
  }, [isNative, owner, text, enabled, progress, visible]);

  useEffect(() => {
    if (isNative) return;
    return () => {
      if (useMainButtonFallback.getState().owner === owner) {
        useMainButtonFallback.setState({ owner: null, visible: false });
      }
    };
  }, [isNative, owner]);

  return { native: isNative };
}
