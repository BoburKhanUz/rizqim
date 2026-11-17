import { describe, expect, it } from 'vitest';
import { channelsOfScope, isNextToken, revOf, RevisionTracker, withRevisionCapture } from './revision';

function tracker(start = 1_000_000) {
  let now = start;
  const revisions = new RevisionTracker({ readTtlMs: 150_000, now: () => now });
  return { revisions, advance: (ms: number) => (now += ms) };
}

describe('tokens', () => {
  it('compares as big integers, beyond 2^53 and 32 bits', () => {
    expect(isNextToken('41', '42')).toBe(true);
    expect(isNextToken('42', '42')).toBe(false);
    expect(isNextToken('42', '44')).toBe(false);
    expect(isNextToken('2147483647', '2147483648')).toBe(true);
    expect(isNextToken('9007199254740993', '9007199254740994')).toBe(true);
    // Number() would call these equal
    expect(isNextToken('9007199254740992', '9007199254740992')).toBe(false);
    expect(isNextToken('abc', '1')).toBe(false);
  });

  it('maps a read scope to the channels it feeds', () => {
    expect(channelsOfScope('branch:5')).toEqual(['pos-branch.5', 'branch.5']);
    expect(channelsOfScope('user:9')).toEqual(['user.9']);
    expect(channelsOfScope('store:3')).toEqual(['store.3']);
    expect(channelsOfScope('nonsense')).toEqual([]);
  });

  it('reads the rev of a channel from a payload, with or without the private- prefix', () => {
    const payload = { order: {}, rev: { 'pos-branch.5': '4711', 'store.3': 90210 } };
    expect(revOf(payload, 'pos-branch.5')).toBe('4711');
    expect(revOf(payload, 'private-pos-branch.5')).toBe('4711');
    expect(revOf(payload, 'store.3')).toBeNull(); // not a decimal string
    expect(revOf({ order: {} }, 'pos-branch.5')).toBeNull();
    expect(revOf(null, 'pos-branch.5')).toBeNull();
    // a heartbeat whose data was encoded twice still names its token
    expect(revOf(JSON.stringify({ at: 't', interval: 30, rev: { 'pos-branch.5': '4711' } }), 'pos-branch.5')).toBe('4711');
    expect(revOf('not json', 'pos-branch.5')).toBeNull();
  });
});

describe('RevisionTracker: events', () => {
  it('next: the token right after the read', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'GET /staff/orders?branch_id=5');
    expect(revisions.onEvent('pos-branch.5', '101')).toBe('next');
    // the following event of the stream is next to the one just handled
    expect(revisions.onEvent('pos-branch.5', '102')).toBe('next');
    // one more event of the same commit (a burst): still the normal refetch
    expect(revisions.onEvent('pos-branch.5', '102')).toBe('next');
  });

  it('same: the read was taken after the change', () => {
    const { revisions } = tracker();
    revisions.noteRead('user:9', '55', 'GET /orders?scope=active');
    expect(revisions.onEvent('user.9', '55')).toBe('same');
  });

  it('missed: a gap, or a token that jumped', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    expect(revisions.onEvent('pos-branch.5', '103')).toBe('missed');
    const other = tracker().revisions;
    other.noteRead('branch:5', '100', 'list');
    expect(other.onEvent('pos-branch.5', '7')).toBe('missed');
  });

  it('every read must be accounted for: an older second read makes it a gap', () => {
    const { revisions } = tracker();
    revisions.noteRead('user:9', '20', 'GET /orders?scope=active');
    revisions.noteRead('user:9', '18', 'GET /notifications');
    // 21 is next to the orders read but two ahead of the notifications read
    expect(revisions.onEvent('user.9', '21')).toBe('missed');
  });

  it('same only when EVERY read is that new', () => {
    const { revisions } = tracker();
    revisions.noteRead('user:9', '20', 'a');
    revisions.noteRead('user:9', '21', 'b');
    // the a-read predates change 21: refetch
    expect(revisions.onEvent('user.9', '21')).toBe('next');
  });

  it('untracked: a channel without single-scope reads (store-wide lists) re-reads on any event as before', () => {
    const { revisions } = tracker();
    expect(revisions.onEvent('store.3', '10')).toBe('untracked');
    expect(revisions.onEvent('store.3', '10')).toBe('untracked');
    expect(revisions.onEvent('store.3', '50')).toBe('untracked');
  });

  it('an event without rev never counts as "same"', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    expect(revisions.onEvent('pos-branch.5', null)).toBe('untracked');
  });

  it('a read stops counting after its time-to-live', () => {
    const { revisions, advance } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    advance(151_000);
    expect(revisions.heldToken('pos-branch.5')).toBeNull();
    // no fresh read and nothing heard: as before
    expect(revisions.onEvent('pos-branch.5', '100')).toBe('untracked');
  });

  it('a new read replaces the same key', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    revisions.noteRead('branch:5', '104', 'list');
    expect(revisions.heldToken('branch.5')).toBe('104');
    expect(revisions.onEvent('branch.5', '105')).toBe('next');
  });

  it('clear() forgets everything (sign-out)', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    revisions.clear();
    expect(revisions.onEvent('pos-branch.5', '100')).toBe('untracked');
  });
});

describe('RevisionTracker: heartbeats', () => {
  it('fine while the heartbeat names the token the screen holds', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    expect(revisions.onHeartbeat('pos-branch.5', '100')).toBe('fine');
  });

  it('reread when the heartbeat is ahead of the read (an event was lost)', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    expect(revisions.onHeartbeat('pos-branch.5', '101')).toBe('reread');
  });

  it('fine when the last event already announced that token (its refetch is under way)', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    revisions.onEvent('pos-branch.5', '101');
    expect(revisions.onHeartbeat('pos-branch.5', '101')).toBe('fine');
  });

  it('a store-wide channel: reread only when its own token moved since the last thing heard', () => {
    const { revisions } = tracker();
    expect(revisions.onHeartbeat('store.3', '7')).toBe('fine');
    expect(revisions.onHeartbeat('store.3', '7')).toBe('fine');
    expect(revisions.onHeartbeat('store.3', '8')).toBe('reread');
    revisions.onEvent('store.3', '9');
    expect(revisions.onHeartbeat('store.3', '9')).toBe('fine');
  });

  it('a heartbeat without rev says nothing', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'list');
    expect(revisions.onHeartbeat('pos-branch.5', null)).toBe('fine');
  });
});

describe('RevisionTracker: fix round 2 (every event fed, the newest token)', () => {
  it('a heartbeat equal to the newest read is fine although an older read under another URL is still held', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'GET /staff/orders?scope=history&branch_id=5');
    revisions.noteRead('branch:5', '104', 'GET /staff/orders/abc');
    revisions.resetEvents();
    expect(revisions.onHeartbeat('pos-branch.5', '104')).toBe('fine');
    expect(revisions.onHeartbeat('pos-branch.5', '105')).toBe('reread');
  });

  it('a heartbeat equal to the newest event stays fine after a re-read reset the events', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '100', 'GET /staff/orders?scope=active&branch_id=5');
    expect(revisions.onEvent('pos-branch.5', '107')).toBe('missed');
    revisions.resetEvents();
    // the re-read the gap asked for is under way: the heartbeat names what it will read
    expect(revisions.onHeartbeat('pos-branch.5', '107')).toBe('fine');
  });

  it('ignored events still move the token: the next relevant one is next, not a gap', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '200', 'GET /pos/floor');
    // fiscal, payment and shift events the screen does not read for
    expect(revisions.onEvent('pos-branch.5', '201')).toBe('next');
    expect(revisions.onEvent('pos-branch.5', '202')).toBe('next');
    expect(revisions.onEvent('pos-branch.5', '203')).toBe('next');
    expect(revisions.onHeartbeat('pos-branch.5', '203')).toBe('fine');
    expect(revisions.onEvent('pos-branch.5', '204')).toBe('next');
  });

  it('a heartbeat ahead of the newest token re-reads, also on a store-wide channel fed by every event', () => {
    const { revisions } = tracker();
    revisions.onEvent('store.3', '10');
    revisions.onEvent('store.3', '11');
    expect(revisions.onHeartbeat('store.3', '11')).toBe('fine');
    expect(revisions.onHeartbeat('store.3', '12')).toBe('reread');
  });

  it('clear() forgets the newest token too', () => {
    const { revisions } = tracker();
    revisions.noteRead('branch:5', '300', 'GET /staff/orders/abc');
    revisions.clear();
    revisions.noteRead('branch:5', '290', 'GET /staff/orders?branch_id=5');
    expect(revisions.onHeartbeat('pos-branch.5', '300')).toBe('reread');
  });
});

describe('withRevisionCapture', () => {
  it('records X-Revision of a successful GET, by method and URL', async () => {
    const { revisions } = tracker();
    const send = withRevisionCapture(
      async () => new Response('{}', { status: 200, headers: { 'X-Revision': '12', 'X-Revision-Scope': 'branch:4' } }),
      revisions,
    );
    await send(new Request('https://api.test/api/v1/staff/orders?branch_id=4'));
    expect(revisions.heldToken('pos-branch.4')).toBe('12');
  });

  it('ignores answers without the headers, failed ones and writes', async () => {
    const { revisions } = tracker();
    const headers = { 'X-Revision': '12', 'X-Revision-Scope': 'branch:4' };
    await withRevisionCapture(async () => new Response('{}', { status: 500, headers }), revisions)(new Request('https://api.test/x'));
    await withRevisionCapture(async () => new Response('{}', { status: 200, headers }), revisions)(new Request('https://api.test/x', { method: 'POST' }));
    await withRevisionCapture(async () => new Response('{}', { status: 200 }), revisions)(new Request('https://api.test/x'));
    expect(revisions.heldToken('pos-branch.4')).toBeNull();
  });
});
