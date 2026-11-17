import 'read_tracking.dart';

/// What to do with an event this screen reads on, from its `rev` ([ChannelRevision.event]).
enum RevisionVerdict {
  /// `rev == R`: a read already shows it. Nothing to do.
  skip,

  /// Anything else (the next change, a gap, no `rev`, nothing proven yet): read.
  read,
}

/// What a `realtime.heartbeat` says ([ChannelRevision.heartbeat]).
enum HeartbeatVerdict {
  /// Its `rev` is S and nothing is in doubt (or nothing is known yet: the safety poll covers that).
  fine,

  /// Its `rev` is not S: a commit happened whose event never reached this screen. Read.
  missed,

  /// Its `rev` is S, but an ignored event jumped ahead of S on the way ([ChannelRevision.doubt]). Tills and the KDS
  /// read; the customer display leaves it to its safety poll.
  doubt,
}

/// The revision state of one screen on one channel (docs/API.md 8, "M2: `rev`, heartbeat, holat").
///
/// Two tokens and a flag (wave M2 fix round 2: before it, a screen only counted the events it read on, so an event it
/// ignored moved the channel's `rev` behind its back and the next relevant event looked like a gap, and every heartbeat
/// forced a read):
/// * **R** ([known]): the token a full read for this screen proved (`X-Revision` through [ReadCapture.revisionFor]).
///   A relevant event whose `rev` is R is already on screen. Only a read sets R; a failed read forgets it.
/// * **S** ([settled]): every commit up to S is accounted for on this screen: read (R), or announced by an event that is
///   read or is irrelevant here. EVERY event of the channel feeds S, relevant or not ([event], [ignored]).
/// * **doubt** ([doubt]): something may be missing although S matches: an ignored event that was not S or S+1 (a jump:
///   the event between was lost or is still on its way) or a failed read. The next full read that proves S clears it.
///
/// Rules: a relevant event reads unless its `rev` is R (and it moves S to its `rev`: the read will cover any gap). An
/// ignored event: `rev` S or S+1 -> S = rev; below S (within [_window], an older event arriving late) -> nothing;
/// anything else -> S = rev and doubt. A heartbeat: `rev != S` -> [HeartbeatVerdict.missed]; `rev == S` with doubt ->
/// [HeartbeatVerdict.doubt]; nothing known yet -> [HeartbeatVerdict.fine]. Tokens are compared only by equality and
/// small distances (the base is random and jumps when Redis is flushed).
///
/// Known backend limit: an event takes its `rev` when the queue SENDS it, not at its own commit. An ignored event sent
/// after a quick second commit carries the second token, looks like a jump and sets doubt: at most one heartbeat read.
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

  /// How far below S a token still counts as "older" (a late event) rather than a new random base.
  static final BigInt _window = BigInt.from(100000);

  BigInt? _read;
  BigInt? _settled;
  bool _doubt = false;

  /// Counters (tests and diagnostics).
  /// Relevant events whose `rev` was S+1 with nothing in doubt.
  int nextCount = 0;

  /// Relevant events skipped because their `rev` was R.
  int sameCount = 0;

  /// Relevant events that were not S+1, or came while in doubt (a gap, no `rev`, nothing known, an ignored event that
  /// jumped before them): their read is also a catch-up.
  int rereadCount = 0;

  /// Ignored events (fed into S only).
  int ignoredCount = 0;

  /// Heartbeats that led to a read ([HeartbeatVerdict.missed], or [HeartbeatVerdict.doubt] where the screen reads).
  int heartbeatRereads = 0;

  /// R: the token a read proved this screen shows (`null`: nothing proven).
  BigInt? get known => _read;

  /// S: every commit up to it is accounted for (`null`: nothing known yet).
  BigInt? get settled => _settled;

  /// Something may be missing although S matches the channel.
  bool get doubt => _doubt;

  /// The `rev` of this channel in [data] (`null` without one, or on an untracked channel).
  BigInt? revOf(Map<String, Object?> data) =>
      scope == null ? null : revisionIn(data, channelKey);

  /// `true` when [a] is below [b] by at most [_window] (an older token of the same base).
  static bool below(BigInt a, BigInt b) {
    final BigInt d = b - a;
    return d > BigInt.zero && d <= _window;
  }

  /// [a] is [b] or below it ([below]): a read that proved [b] shows [a].
  static bool covers(BigInt b, BigInt a) => a == b || below(a, b);

  /// An event this screen reads on.
  RevisionVerdict event(Map<String, Object?> data) {
    final BigInt? rev = revOf(data);
    final BigInt? r = _read;
    if (rev != null && r != null && rev == r) {
      sameCount++;
      return RevisionVerdict.skip;
    }
    final BigInt? s = _settled;
    if (rev != null && s != null && rev == s + BigInt.one && !_doubt) {
      nextCount++;
    } else {
      rereadCount++;
    }
    if (rev != null && (s == null || !below(rev, s))) _settled = rev;
    return RevisionVerdict.read;
  }

  /// An event this screen does not read on: it only accounts for its commit.
  void ignored(Map<String, Object?> data) {
    ignoredCount++;
    final BigInt? rev = revOf(data);
    final BigInt? s = _settled;
    if (rev == null || s == null) return;
    if (rev == s || rev == s + BigInt.one) {
      _settled = rev;
    } else if (below(rev, s)) {
      // An older event arriving late: already accounted for
    } else {
      _settled = rev;
      _doubt = true;
    }
  }

  /// A `realtime.heartbeat` on the channel. [readOnDoubt]: this screen reads on [HeartbeatVerdict.doubt] (counted in
  /// [heartbeatRereads]); never adopts the heartbeat's token.
  HeartbeatVerdict heartbeat(
    Map<String, Object?> data, {
    bool readOnDoubt = true,
  }) {
    final BigInt? rev = revOf(data);
    final BigInt? s = _settled;
    if (rev == null || s == null) return HeartbeatVerdict.fine;
    if (rev != s) {
      heartbeatRereads++;
      return HeartbeatVerdict.missed;
    }
    if (!_doubt) return HeartbeatVerdict.fine;
    if (readOnDoubt) heartbeatRereads++;
    return HeartbeatVerdict.doubt;
  }

  /// A full read for this screen finished: [revision] is what [ReadCapture.revisionFor] proved (`null`: nothing
  /// proven, R is forgotten). A proof at or above S settles S and clears the doubt.
  void read(BigInt? revision) {
    if (scope == null || revision == null) {
      _read = null;
      return;
    }
    _read = revision;
    final BigInt? s = _settled;
    if (s == null || !below(revision, s)) {
      _settled = revision;
      _doubt = false;
    }
  }

  /// A read failed: what the screen shows is unknown, and what it was asked to show may be missing.
  void forget() {
    _read = null;
    if (_settled != null) _doubt = true;
  }

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
