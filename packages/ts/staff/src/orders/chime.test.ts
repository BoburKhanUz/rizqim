import { afterEach, describe, expect, it, vi } from 'vitest';
import { createChime } from './chime';

afterEach(() => vi.unstubAllGlobals());

class FakeAudioContext {
  static instances: FakeAudioContext[] = [];
  state = 'suspended';
  currentTime = 0;
  destination = {};
  oscillators = 0;
  constructor() {
    FakeAudioContext.instances.push(this);
  }
  async resume() {
    this.state = 'running';
  }
  async close() {
    this.state = 'closed';
  }
  createOscillator() {
    this.oscillators += 1;
    return { type: '', frequency: { value: 0 }, connect: (node: unknown) => node, start: vi.fn(), stop: vi.fn() };
  }
  createGain() {
    const gain = { gain: { setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() }, connect: (node: unknown) => node };
    return gain;
  }
}

describe('the new order chime', () => {
  it('stays silent until a gesture unlocks the audio, then plays two tones', async () => {
    vi.stubGlobal('AudioContext', FakeAudioContext);
    const chime = createChime();
    expect(chime.ready).toBe(false);
    expect(chime.play()).toBe(false);
    expect(await chime.unlock()).toBe(true);
    expect(chime.ready).toBe(true);
    expect(chime.play()).toBe(true);
    expect(FakeAudioContext.instances.at(-1)!.oscillators).toBe(2);
  });

  it('does nothing in a browser without Web Audio', async () => {
    vi.stubGlobal('AudioContext', undefined);
    const chime = createChime();
    expect(await chime.unlock()).toBe(false);
    expect(chime.play()).toBe(false);
  });

  it('gives the audio device back when closed, and a later gesture opens a new one', async () => {
    vi.stubGlobal('AudioContext', FakeAudioContext);
    const chime = createChime();
    await chime.unlock();
    const first = FakeAudioContext.instances.at(-1)!;
    chime.close();
    expect(first.state).toBe('closed');
    expect(chime.ready).toBe(false);
    expect(chime.play()).toBe(false);
    expect(await chime.unlock()).toBe(true);
    expect(FakeAudioContext.instances.at(-1)).not.toBe(first);
  });
});
