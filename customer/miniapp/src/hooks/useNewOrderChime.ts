import { useCallback, useEffect, useRef, useState } from 'react';
import { createChime, type Chime, type StaffOrder } from '@rizqim/staff';

// The mute switch, remembered on this phone (the partner web keeps its own under the same key)
export const ORDER_SOUND_KEY = 'rz_order_sound';

function readMuted(): boolean {
  try {
    return localStorage.getItem(ORDER_SOUND_KEY) === 'off';
  } catch {
    return false;
  }
}

function writeMuted(muted: boolean): void {
  try {
    localStorage.setItem(ORDER_SOUND_KEY, muted ? 'off' : 'on');
  } catch {
    // The switch then holds for this visit only
  }
}

export interface NewOrderChime {
  muted: boolean;
  toggleMuted: () => void;
  // Sound is on but Telegram's browser has not allowed it yet: the screen asks for one tap
  needsGesture: boolean;
}

// The new-order chime of the staff orders screen (the partner web's, from @rizqim/staff: two tones of the Web Audio API,
// no file). Telegram's in-app browser, like any browser, plays sound only after a tap on the page: the first tap or key
// press unlocks it. A chime sounds when an order in status `new` appears that the list did not have before; what is there
// when the screen opens, or when the list switches to another branch (`scope`), is not announced.
export function useNewOrderChime(orders: readonly Pick<StaffOrder, 'uuid' | 'status'>[] | undefined, scope: string, givenChime?: Chime): NewOrderChime {
  const [chime] = useState(() => givenChime ?? createChime());
  const [muted, setMuted] = useState(readMuted);
  const [ready, setReady] = useState(() => chime.ready);

  // The audio device goes with the screen
  useEffect(() => () => chime.close(), [chime]);

  useEffect(() => {
    const unlock = () => {
      if (chime.ready) return;
      void chime.unlock().then((unlocked) => {
        if (unlocked) setReady(true);
      });
    };
    // A click (a tap) or a key press: both count as the user's gesture for audio. Not pointerdown: the hint goes away when
    // sound unlocks, and a layout shift between pointerdown and click would make the tap miss the button it was aimed at.
    const events = ['click', 'keydown'] as const;
    events.forEach((name) => window.addEventListener(name, unlock));
    return () => events.forEach((name) => window.removeEventListener(name, unlock));
  }, [chime]);

  const seen = useRef<{ scope: string; uuids: Set<string> } | null>(null);
  useEffect(() => {
    if (!orders) return;
    const fresh = orders.filter((order) => order.status === 'new').map((order) => order.uuid);
    const previous = seen.current;
    seen.current = { scope, uuids: new Set(orders.map((order) => order.uuid)) };
    if (!previous || previous.scope !== scope) return;
    if (!muted && fresh.some((uuid) => !previous.uuids.has(uuid))) chime.play();
  }, [orders, scope, muted, chime]);

  const toggleMuted = useCallback(() => {
    setMuted((previous) => {
      writeMuted(!previous);
      return !previous;
    });
  }, []);

  // Without Web Audio there is nothing to unlock (and nothing to ask for)
  const supported = typeof window !== 'undefined' && ('AudioContext' in window || 'webkitAudioContext' in window);
  return { muted, toggleMuted, needsGesture: supported && !muted && !ready };
}
