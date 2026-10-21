import type { EchoChannelLike, EchoLike } from '@/lib/realtime';

// A laravel-echo stand-in for the real-time tests: records the channels, lets the test fire events, subscription
// results and connection changes.
export class FakeChannel implements EchoChannelLike {
  listeners = new Map<string, (payload: unknown) => void>();
  onSubscribed: (() => void) | null = null;
  onError: ((error: unknown) => void) | null = null;
  constructor(readonly name: string) {}
  listen(event: string, callback: (payload: unknown) => void) {
    this.listeners.set(event, callback);
    return this;
  }
  subscribed(callback: () => void) {
    this.onSubscribed = callback;
    return this;
  }
  error(callback: (error: unknown) => void) {
    this.onError = callback;
    return this;
  }
  emit(event: string, payload: unknown) {
    this.listeners.get(`.${event}`)?.(payload);
  }
}

export class FakeEcho implements EchoLike {
  channels: FakeChannel[] = [];
  left: string[] = [];
  disconnected = false;
  private connection: ((status: string) => void) | null = null;
  connector = {
    onConnectionChange: (callback: (status: string) => void) => {
      this.connection = callback;
      return () => {
        this.connection = null;
      };
    },
  };
  private(name: string) {
    const channel = new FakeChannel(name);
    this.channels.push(channel);
    return channel;
  }
  leave(name: string) {
    this.left.push(name);
  }
  disconnect() {
    this.disconnected = true;
  }
  setConnection(status: string) {
    this.connection?.(status);
  }
  // The latest subscription of a channel
  channel(name: string): FakeChannel {
    const found = [...this.channels].reverse().find((channel) => channel.name === name);
    if (!found) throw new Error(`No channel ${name}`);
    return found;
  }
  // Socket up and every channel subscribed
  connectAll() {
    this.setConnection('connected');
    for (const channel of this.channels) channel.onSubscribed?.();
  }
}
