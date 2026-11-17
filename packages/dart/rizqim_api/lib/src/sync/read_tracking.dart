import 'dart:async';

import 'package:dio/dio.dart';

import 'conditional_get.dart';

/// One GET seen by the [ConditionalGetInterceptor] inside a [ReadCapture].
class ReadRecord {
  ReadRecord._(this.startSeq, this.path);

  /// Global order of starts and completions ([ReadTracking]'s counter).
  final int startSeq;
  final String path;
  int? endSeq;
  bool failed = false;

  /// `X-Revision` of the body (for a `304`: of the kept body, still current by definition of a `304`).
  String? revision;

  /// `X-Revision-Scope` (`branch:12`).
  String? scope;

  /// Answered from the [EtagStore] after a `304`.
  bool replayed = false;
  ConditionalGetEntry? entry;

  bool get done => endSeq != null;
}

/// The GETs made while a piece of code ran ([ReadTracking.capture]): which revision the screen's data reflects and when
/// its bodies were received.
class ReadCapture {
  ReadCapture._(this._parent);

  final ReadCapture? _parent;
  final List<ReadRecord> reads = <ReadRecord>[];

  void _add(ReadRecord r) {
    reads.add(r);
    _parent?._add(r);
  }

  /// Some read failed (network, 4xx/5xx) or had not finished when the code returned.
  bool get failed => reads.any((ReadRecord r) => r.failed || !r.done);

  /// Some read was answered from the store after a `304`.
  bool get anyReplayed => reads.any((ReadRecord r) => r.replayed);

  /// The change token the captured data is known to include for [scope] (`branch:12`), or `null` when that cannot be
  /// proven:
  /// * no read named [scope] with an `X-Revision`, or a read failed;
  /// * another read (a store-wide list, a read without revision, another scope) STARTED before the first [scope] read
  ///   had completed: that read may hold data from before the token was taken, so the token proves nothing about it.
  /// With several [scope] reads the smallest token is used (each token was read before its body, so every change up to
  /// the smallest is in all of them).
  BigInt? revisionFor(String scope) {
    if (reads.isEmpty || failed) return null;
    final List<ReadRecord> own = <ReadRecord>[
      for (final ReadRecord r in reads)
        if (r.scope == scope && parseRevision(r.revision) != null) r,
    ];
    if (own.isEmpty) return null;
    final int firstDone = own
        .map((ReadRecord r) => r.endSeq!)
        .reduce((int a, int b) => a < b ? a : b);
    for (final ReadRecord r in reads) {
      if (own.contains(r)) continue;
      if (r.startSeq < firstDone) return null;
    }
    BigInt? min;
    for (final ReadRecord r in own) {
      final BigInt v = parseRevision(r.revision)!;
      if (min == null || v < min) min = v;
    }
    return min;
  }

  /// When the captured data was received: the earliest receive moment among the reads, where a body answered after a
  /// `304` counts from its original `200` (so timers counted from it do not restart). Fresh bodies count as [now] and
  /// their stored entries are stamped with it (this caller's clock).
  DateTime receivedAt(DateTime now) {
    DateTime? earliest;
    for (final ReadRecord r in reads) {
      if (r.failed || !r.done) continue;
      final DateTime at;
      final ConditionalGetEntry? e = r.entry;
      if (r.replayed && e != null) {
        at = e.receivedAt;
      } else {
        if (e != null) e.receivedAt = now;
        at = now;
      }
      if (earliest == null || at.isBefore(earliest)) earliest = at;
    }
    return earliest ?? now;
  }
}

/// The value [ReadTracking.capture] ran for, with its [ReadCapture].
class CapturedRead<T> {
  const CapturedRead(this.value, this.reads);

  final T value;
  final ReadCapture reads;
}

/// Zone-scoped tracking of the GETs a refresh makes, without threading anything through the repositories: the
/// [ConditionalGetInterceptor] reports every GET to the capture of the zone the request was made in.
abstract final class ReadTracking {
  static const Symbol _captureKey = #rizqimReadCapture;
  static const Symbol _unconditionalKey = #rizqimUnconditionalRead;
  static const String _recordExtra = 'rizqim.read.record';
  static const String _captureExtra = 'rizqim.read.capture';
  static int _seq = 0;

  /// Runs [body] and returns what it returned with the GETs it made. [unconditional]: those GETs are sent without
  /// `If-None-Match` (a full `200`; the body is stored again). Captures nest: an outer capture sees the inner reads.
  static Future<CapturedRead<T>> capture<T>(
    Future<T> Function() body, {
    bool unconditional = false,
  }) async {
    final ReadCapture capture = ReadCapture._(
      Zone.current[_captureKey] as ReadCapture?,
    );
    final T value = await runZoned(
      body,
      zoneValues: <Object?, Object?>{
        _captureKey: capture,
        if (unconditional) _unconditionalKey: true,
      },
    );
    return CapturedRead<T>(value, capture);
  }

  /// The code runs inside an unconditional capture.
  static bool get unconditional => Zone.current[_unconditionalKey] == true;

  static void started(RequestOptions options) {
    // A retried request (a 401 refreshed and sent again) keeps its first record
    if (options.extra[_recordExtra] is ReadRecord) return;
    final ReadCapture? capture = Zone.current[_captureKey] as ReadCapture?;
    if (capture == null) return;
    final ReadRecord record = ReadRecord._(++_seq, options.uri.path);
    options.extra[_recordExtra] = record;
    options.extra[_captureExtra] = capture;
    capture._add(record);
  }

  static void completed(
    RequestOptions options, {
    required String? revision,
    required String? scope,
    required ConditionalGetEntry? entry,
    required bool replayed,
  }) {
    final Object? r = options.extra[_recordExtra];
    if (r is! ReadRecord || r.done) return;
    r
      ..endSeq = ++_seq
      ..revision = revision
      ..scope = scope
      ..entry = entry
      ..replayed = replayed;
  }

  static void failed(RequestOptions options) {
    final Object? r = options.extra[_recordExtra];
    if (r is! ReadRecord || r.done) return;
    r
      ..endSeq = ++_seq
      ..failed = true;
  }
}

/// A change token (`"4711"`) as a number; `null` for anything that is not a decimal string. Tokens are compared only
/// for equality and "+1" (docs/API.md 8, M2), as [BigInt] so no platform int size matters.
BigInt? parseRevision(Object? value) {
  if (value is int) return BigInt.from(value);
  if (value is! String) return null;
  final String s = value.trim();
  if (s.isEmpty || s.length > 30 || !RegExp(r'^[0-9]+$').hasMatch(s)) {
    return null;
  }
  return BigInt.parse(s);
}
