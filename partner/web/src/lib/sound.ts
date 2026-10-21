// The new-order chime: two short tones made with the Web Audio API (no sound file to load). Browsers only let a page
// play sound after the person has interacted with it (autoplay rules): the AudioContext is created or resumed on the
// first click or key press, and until then `ready` is false and the page shows a hint.

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
  };
}

// The mute switch, remembered per browser
export const SOUND_STORAGE_KEY = 'rz_order_sound';
