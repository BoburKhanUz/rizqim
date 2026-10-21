// The new-order chime of the staff screens (partner web, Mini App): two short tones made with the Web Audio API (no sound
// file to load). Browsers, Telegram's in-app browser included, only let a page play sound after the person has
// interacted with it (autoplay rules): the AudioContext is created or resumed on the first tap or key press, and until
// then `ready` is false and the screen shows a hint. `close` gives the audio device back when the screen goes away.

type AudioContextCtor = new () => AudioContext;

function audioContextClass(): AudioContextCtor | null {
  if (typeof window === 'undefined') return null;
  const scope = window as unknown as { AudioContext?: AudioContextCtor; webkitAudioContext?: AudioContextCtor };
  return scope.AudioContext ?? scope.webkitAudioContext ?? null;
}

export interface Chime {
  // Sound can play (the context exists and runs)
  readonly ready: boolean;
  // Call from a user gesture: creates or resumes the context. Resolves to `ready`.
  unlock: () => Promise<boolean>;
  // Plays the chime if it can; returns whether it did
  play: () => boolean;
  // Releases the AudioContext (browsers allow only a few at a time); a later unlock creates a new one
  close: () => void;
}

export function createChime(): Chime {
  let context: AudioContext | null = null;

  const isReady = () => context !== null && context.state === 'running';

  return {
    get ready() {
      return isReady();
    },
    unlock: async () => {
      const Ctor = audioContextClass();
      if (!Ctor) return false;
      try {
        context ??= new Ctor();
        if (context.state === 'suspended') await context.resume();
      } catch {
        return false;
      }
      return isReady();
    },
    play: () => {
      if (!context || !isReady()) return false;
      try {
        const start = context.currentTime;
        [880, 1320].forEach((frequency, index) => {
          const at = start + index * 0.18;
          const oscillator = context!.createOscillator();
          const gain = context!.createGain();
          oscillator.type = 'sine';
          oscillator.frequency.value = frequency;
          gain.gain.setValueAtTime(0.0001, at);
          gain.gain.exponentialRampToValueAtTime(0.35, at + 0.02);
          gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.16);
          oscillator.connect(gain).connect(context!.destination);
          oscillator.start(at);
          oscillator.stop(at + 0.17);
        });
        return true;
      } catch {
        return false;
      }
    },
    close: () => {
      const closing = context;
      context = null;
      void closing?.close().catch(() => undefined);
    },
  };
}
