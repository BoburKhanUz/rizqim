import 'read_tracking.dart';

/// What to do with an event, from its `rev` ([ChannelRevision.event]).
enum RevisionVerdict {
  /// `rev == N + 1`: the next change. Handle it as before (debounced re-read or apply); N becomes N + 1.
  next,

  /// `rev == N`: already reflected in what the screen read (or announced by an event already handled). Nothing to do.
  same,

  /// Anything else, no `rev`, or no known N: something may have been missed. Re-read everything.
  reread,
}

/// The revision state of one screen on one channel (docs/API.md 8, "M2: `rev`, heartbeat, holat").
///
/// N is known only from proof: the `X-Revision` of a full read made for this screen ([read], from
/// [ReadCapture.revisionFor]) or an event handled as [RevisionVerdict.next]. Anything doubtful forgets it, and an
/// unknown N makes every event a re-read (the behaviour before revisions existed). Never adopted from a heartbeat.
///
/// One tracker per screen, not per app: a read made by another screen proves nothing about this screen's data.
class ChannelRevision {
  ChannelRevision(String channel)
    : channelKey = channelKeyOf(channel),
      scope = scopeOfChannel(channelKeyOf(channel));

  /// The channel name without its `private-` prefix (`pos-branch.5`), as the server keys `rev`.
  final String channelKey;

  /// The read scope whose `X-Revision` matches this channel's `rev` (`branch:5`), or `null` (no revision scope).
  final String? scope;

  BigInt? _known;

  /// Counters (tests and diagnostics).
  int nextCount = 0;
  int sameCount = 0;
  int rereadCount = 0;
  int heartbeatRereads = 0;

  /// The token this screen is known to reflect.
  BigInt? get known => _known;

  /// An event on the channel (its decoded `data`).
  RevisionVerdict event(Map<String, Object?> data) {
    final BigInt? rev = scope == null ? null : revisionIn(data, channelKey);
    final BigInt? n = _known;
    if (rev != null && n != null) {
      if (rev == n + BigInt.one) {
        _known = rev;
        nextCount++;
        return RevisionVerdict.next;
      }
      if (rev == n) {
        sameCount++;
        return RevisionVerdict.same;
      }
    }
    _known = null;
    rereadCount++;
    return RevisionVerdict.reread;
  }

  /// A `realtime.heartbeat` on the channel: `true` when its `rev` differs from N (an event was missed: re-read).
  /// Without a known N (or without `rev`) a heartbeat proves nothing either way: the safety poll covers it.
  bool heartbeat(Map<String, Object?> data) {
    final BigInt? rev = scope == null ? null : revisionIn(data, channelKey);
    final BigInt? n = _known;
    if (rev == null || n == null || rev == n) return false;
    _known = null;
    heartbeatRereads++;
    return true;
  }

  /// A full read for this screen finished: [revision] is what [ReadCapture.revisionFor] proved (null forgets N).
  void read(BigInt? revision) => _known = scope == null ? null : revision;

  /// A read failed or proved nothing.
  void forget() => _known = null;

  /// `private-pos-branch.5` -> `pos-branch.5`.
  static String channelKeyOf(String channel) => channel.replaceFirst(
    RegExp(r'^(private-encrypted-|private-|presence-)'),
    '',
  );

  /// `pos-branch.5` / `branch.5` -> `branch:5`, `user.9` -> `user:9`. Store and courier channels are untracked
  /// (`null`: every event re-reads): a branch-only change does not move the store token (`touchBranch` touches the
  /// branch alone), so an unchanged `store.N` rev proves nothing about a screen's data; a courier's list is store-wide.
  static String? scopeOfChannel(String channelKey) {
    final RegExpMatch? m = RegExp(r'^(pos-branch|branch|user)\.(\d+)$')
        .firstMatch(channelKey);
    if (m == null) return null;
    return m.group(1) == 'user' ? 'user:${m.group(2)}' : 'branch:${m.group(2)}';
  }

  /// The `rev` of [channelKey] in an event body, or `null`.
  static BigInt? revisionIn(Map<String, Object?> data, String channelKey) {
    final Object? rev = data['rev'];
    if (rev is! Map) return null;
    return parseRevision(rev[channelKey]);
  }
}
