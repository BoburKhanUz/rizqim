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

  leave(name: string) {
    this.left.push(name);
  }

  disconnect() {
    this.disconnected = true;
  }

  // --- the server's side ---

  get channel(): FakeChannel {
    return this.channels[this.channels.length - 1];
  }

  get watchers(): number {
    return this.connectionCallbacks.size;
  }

  setConnection(status: FakeStatus) {
    for (const callback of [...this.connectionCallbacks]) callback(status);
  }

  acceptSubscription() {
    for (const callback of this.channel.subscribedCallbacks) callback();
  }

  refuseSubscription() {
    for (const callback of this.channel.errorCallbacks) callback();
  }

  emit(event: string, payload: unknown) {
    for (const callback of this.channel.listeners.get(event) ?? []) (callback as (payload: unknown) => void)(payload);
  }

  // The usual start: the socket connects, then the channel is accepted.
  connectAndSubscribe() {
    this.setConnection('connected');
    this.acceptSubscription();
  }
}

export class FakePusher {}
