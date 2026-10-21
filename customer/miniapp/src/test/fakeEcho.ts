// A stand-in for laravel-echo's Echo, for tests: `vi.mock('laravel-echo', async () => ({ default: (await import('../test/fakeEcho')).FakeEcho }))`.
// Records what the app asked for and lets a test play the server: connection changes, subscription results, events.

type Callback = (...args: never[]) => void;
export type FakeStatus = 'connected' | 'connecting' | 'disconnected' | 'failed';

export class FakeChannel {
  listeners = new Map<string, Callback[]>();
  subscribedCallbacks: Callback[] = [];
  errorCallbacks: Callback[] = [];

  readonly name: string;

  constructor(name: string) {
    this.name = name;
  }

  listen(event: string, callback: Callback) {
    this.listeners.set(event, [...(this.listeners.get(event) ?? []), callback]);
    return this;
  }

  subscribed(callback: Callback) {
    this.subscribedCallbacks.push(callback);
    return this;
  }

  error(callback: Callback) {
    this.errorCallbacks.push(callback);
    return this;
  }
}

export const echoInstances: FakeEcho[] = [];

// Set to make the next `new Echo(...)` throw, as a client that cannot be created does.
export const echoFailure: { error: Error | null } = { error: null };

export class FakeEcho {
  channels: FakeChannel[] = []; // every channel handed out, in order (a re-subscription is a new entry)
  left: string[] = [];
  disconnected = false;
  private connectionCallbacks = new Set<(status: FakeStatus) => void>();
  readonly connector = {
    onConnectionChange: (callback: (status: FakeStatus) => void) => {
      this.connectionCallbacks.add(callback);
      return () => this.connectionCallbacks.delete(callback);
    },
  };

  readonly options: Record<string, unknown>;

  constructor(options: Record<string, unknown>) {
    if (echoFailure.error) throw echoFailure.error;
    this.options = options;
    echoInstances.push(this);
  }

  private(name: string) {
    const channel = new FakeChannel(name);
    this.channels.push(channel);
    return channel;
  }

  // A public channel (no authorization), like Echo.channel(): recorded with the private ones
  channel(name: string) {
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

  // --- the server's side ---

  // The latest subscription of a channel by name (a re-subscription is a new entry), the first channel asked for by default
  channelNamed(name?: string): FakeChannel {
    const wanted = name ?? this.channels[0]?.name;
    const found = this.channels.filter((channel) => channel.name === wanted);
    return found[found.length - 1];
  }

  // The first channel asked for (private or public)
  get firstChannel(): FakeChannel {
    return this.channelNamed();
  }

  get watchers(): number {
    return this.connectionCallbacks.size;
  }

  setConnection(status: FakeStatus) {
    for (const callback of [...this.connectionCallbacks]) callback(status);
  }

  acceptSubscription(name?: string) {
    for (const callback of this.channelNamed(name).subscribedCallbacks) callback();
  }

  refuseSubscription(name?: string) {
    for (const callback of this.channelNamed(name).errorCallbacks) callback();
  }

  emit(event: string, payload: unknown, name?: string) {
    for (const callback of this.channelNamed(name).listeners.get(event) ?? []) (callback as (payload: unknown) => void)(payload);
  }

  // The usual start: the socket connects, then every channel is accepted.
  connectAndSubscribe() {
    this.setConnection('connected');
    for (const name of new Set(this.channels.map((channel) => channel.name))) this.acceptSubscription(name);
  }
}

export class FakePusher {}
