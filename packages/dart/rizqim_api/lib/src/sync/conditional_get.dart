import 'dart:collection';

import 'package:dio/dio.dart';

import 'read_tracking.dart';

/// The last `200` of one polled GET: its body, `ETag`, `X-Revision`/scope and the moment it was received.
///
/// A `304 Not Modified` answer is turned back into this body ([ConditionalGetInterceptor]); [receivedAt] then stays the
/// moment of the ORIGINAL `200`, because timers in a body (`waiting_seconds`, `open_minutes`, `server_time`) count from
/// that moment (docs/API.md 7.1).
class ConditionalGetEntry {
  ConditionalGetEntry({
    required this.etag,
    required this.data,
    required this.headers,
    required this.statusCode,
    required this.receivedAt,
    this.revision,
    this.revisionScope,
  });

  final String etag;
  final Object? data;
  final Map<String, List<String>> headers;
  final int statusCode;

  /// When the `200` arrived. Set by the interceptor's clock; the first reader that stamps it with its own clock
  /// ([ReadCapture.receivedAt], [receivedAtOf]) replaces it, so a screen's timers use one clock.
  DateTime receivedAt;

  /// `X-Revision`: the change token of [revisionScope] read before this body (null for a store-wide body).
  final String? revision;

  /// `X-Revision-Scope`: `branch:12`, `store:3`, `user:9`.
  final String? revisionScope;
}

/// Wave M2: the last `200` of each polled GET, per URL (path + query) and actor (the `Authorization` header), so the
/// next poll sends `If-None-Match` and a `304` costs the server one revision lookup.
///
/// Bounded: at most [maxEntries] (least recently used goes first) and no body above [maxEntryBytes] (by
/// `Content-Length`). Cleared on every sign-in change ([clear]: staff PIN sign-in/out, terminal pairing, user
/// sign-in/out; `RizqimClient` wires it) and when the Dio base URL changes. Never answers on its own: an entry is only
/// used after the server itself said `304` to the request that named it, so it cannot hide that the server is
/// unreachable (a network error is still a network error, and the offline paths above the HTTP layer decide).
class EtagStore {
  EtagStore({this.maxEntries = 64, this.maxEntryBytes = 2 * 1024 * 1024});

  /// A till keeps a handful of screens open (floor, orders, one or two orders and their bills, the queue, the kitchen
  /// board): 64 covers them with room for the order screens a waiter opens during a shift.
  final int maxEntries;
  final int maxEntryBytes;

  final LinkedHashMap<String, ConditionalGetEntry> _entries =
      LinkedHashMap<String, ConditionalGetEntry>();

  /// Counters (tests and diagnostics).
  int stored = 0;
  int evicted = 0;
  int notModified = 0;
  int conditionalRequests = 0;

  int get length => _entries.length;

  /// The entry for [key] (and marks it recently used).
  ConditionalGetEntry? lookup(String key) {
    final ConditionalGetEntry? e = _entries.remove(key);
    if (e != null) _entries[key] = e;
    return e;
  }

  /// The entry for [key] without touching the LRU order (tests).
  ConditionalGetEntry? peek(String key) => _entries[key];

  void put(String key, ConditionalGetEntry entry) {
    _entries.remove(key);
    _entries[key] = entry;
    stored++;
    while (_entries.length > maxEntries) {
      _entries.remove(_entries.keys.first);
      evicted++;
    }
  }

  void remove(String key) => _entries.remove(key);

  void clear() => _entries.clear();

  Iterable<String> get keys => _entries.keys;

  /// The store key of a request: the full URL (base URL, path, sorted query) and the actor.
  static String keyFor(RequestOptions options) {
    final Object? auth =
        options.headers['Authorization'] ?? options.headers['authorization'];
    return '${normalizedUri(options.uri)}\n${auth ?? ''}';
  }

  /// [uri] with its query parameters sorted (`?a=1&b=2` and `?b=2&a=1` are one entry, as on the server).
  static String normalizedUri(Uri uri) {
    if (!uri.hasQuery) return uri.toString();
    final List<MapEntry<String, List<String>>> params =
        uri.queryParametersAll.entries.toList()..sort(
          (
            MapEntry<String, List<String>> a,
            MapEntry<String, List<String>> b,
          ) => a.key.compareTo(b.key),
        );
    return uri
        .replace(
          queryParameters: <String, List<String>>{
            for (final MapEntry<String, List<String>> p in params)
              p.key: p.value,
          },
        )
        .toString();
  }
}

/// The polled GETs the server answers conditionally (docs/API.md 7.1 and "Market o'qish modeli (M2)"). An allow-list,
/// not "any response with an ETag": a body is only ever reused for an endpoint whose ETag the server builds from its
/// change tokens. `/pos/sync/status`, `/pos/bills/{uuid}/qr`, `/pos/bill-print-requests`, the bootstrap and the owner's
/// finance pages are deliberately not here (they are read fresh every time).
final List<RegExp> conditionalGetPaths = <RegExp>[
  RegExp(r'/v1/pos/(floor|orders|queue|display/state|display/queue)$'),
  RegExp(r'/v1/pos/orders/[0-9a-fA-F-]{36}(/bills)?$'),
  RegExp(r'/v1/staff/kitchen/tickets$'),
  RegExp(r'/v1/staff/orders(/[0-9a-fA-F-]{36})?$'),
  RegExp(r'/v1/owner/ingredients$'),
  RegExp(r'/v1/(orders|notifications|stores|catalog|catalog/availability)$'),
];

bool isConditionalGetPath(String path) =>
    conditionalGetPaths.any((RegExp r) => r.hasMatch(path));

const String _keyExtra = 'rizqim.etag.key';
const String _sentExtra = 'rizqim.etag.sent';
const String _validateExtra = 'rizqim.etag.validate';

/// [Response.extra] key: the [ConditionalGetEntry] a `304` was answered from.
const String replayedFromExtra = 'rizqim.etag.replayed';

/// [Response.extra] key: the [ConditionalGetEntry] a fresh `200` was stored as.
const String storedAsExtra = 'rizqim.etag.stored';

/// Sends `If-None-Match` on the [conditionalGetPaths] and turns a `304` into the kept body.
///
/// Add it after the interceptor that sets `Authorization` (the store key includes the actor). On a `304` the response
/// handed on is the kept `200` (status, headers including the original `Date`, a copy of the body) with
/// [replayedFromExtra] set; callers that keep a receive time use [receivedAtOf] or [ReadCapture.receivedAt].
///
/// Inside [ReadTracking.capture] with `unconditional: true` no `If-None-Match` is sent (a full `200`), so a change the
/// server failed to record in its tokens cannot keep a screen stale for longer than the caller's unconditional period.
class ConditionalGetInterceptor extends Interceptor {
  ConditionalGetInterceptor(this.store, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final EtagStore store;
  final DateTime Function() _clock;
  String? _baseUrl;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (options.method == 'GET') {
      ReadTracking.started(options);
      // A changed base URL (another server): nothing kept belongs to it
      if (_baseUrl != null && _baseUrl != options.baseUrl) store.clear();
      _baseUrl = options.baseUrl;
      options.headers.remove('If-None-Match');
      options.extra.remove(_sentExtra);
      if (isConditionalGetPath(options.uri.path)) {
        final String key = EtagStore.keyFor(options);
        options.extra[_keyExtra] = key;
        final ConditionalGetEntry? kept = ReadTracking.unconditional
            ? null
            : store.lookup(key);
        if (kept != null) {
          options.headers['If-None-Match'] = kept.etag;
          options.extra[_sentExtra] = kept;
          store.conditionalRequests++;
          final ValidateStatus original =
              (options.extra[_validateExtra] as ValidateStatus?) ??
              options.validateStatus;
          options.extra[_validateExtra] = original;
          options.validateStatus = (int? status) =>
              status == 304 || original(status);
        }
      }
    }
    handler.next(options);
  }

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    final RequestOptions options = response.requestOptions;
    if (options.method != 'GET') return handler.next(response);
    final String? key = options.extra[_keyExtra] as String?;
    final ConditionalGetEntry? sent =
        options.extra[_sentExtra] as ConditionalGetEntry?;
    if (response.statusCode == 304 && sent != null) {
      store.notModified++;
      final Response<dynamic> replay = Response<dynamic>(
        requestOptions: options,
        data: _copy(sent.data),
        statusCode: sent.statusCode,
        statusMessage: 'OK',
        headers: Headers.fromMap(<String, List<String>>{
          for (final MapEntry<String, List<String>> h in sent.headers.entries)
            h.key: List<String>.of(h.value),
        }),
        extra: <String, dynamic>{...response.extra, replayedFromExtra: sent},
      );
      ReadTracking.completed(
        options,
        revision: sent.revision,
        scope: sent.revisionScope,
        entry: sent,
        replayed: true,
      );
      return handler.next(replay);
    }
    String? revision = response.headers.value('x-revision');
    String? scope = response.headers.value('x-revision-scope');
    ConditionalGetEntry? entry;
    if (key != null && response.statusCode == 200) {
      final String? etag = response.headers.value('etag');
      final int length =
          int.tryParse(response.headers.value('content-length') ?? '') ?? 0;
      if (etag != null && etag.isNotEmpty && length <= store.maxEntryBytes) {
        entry = ConditionalGetEntry(
          etag: etag,
          data: _copy(response.data),
          headers: <String, List<String>>{
            for (final MapEntry<String, List<String>> h
                in response.headers.map.entries)
              h.key: List<String>.of(h.value),
          },
          statusCode: 200,
          receivedAt: _clock(),
          revision: revision,
          revisionScope: scope,
        );
        store.put(key, entry);
        response.extra[storedAsExtra] = entry;
      } else {
        store.remove(key);
      }
    }
    if (revision != null && (scope == null || scope.isEmpty)) revision = null;
    if (scope != null && scope.isEmpty) scope = null;
    ReadTracking.completed(
      options,
      revision: revision,
      scope: scope,
      entry: entry,
      replayed: false,
    );
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final RequestOptions options = err.requestOptions;
    if (options.method == 'GET') {
      // A refused read (401, 403, 404, 5xx) proves nothing about the kept body: forget it
      final String? key = options.extra[_keyExtra] as String?;
      if (key != null && err.response != null) store.remove(key);
      ReadTracking.failed(options);
    }
    handler.next(err);
  }

  /// A deep copy of a decoded JSON body (callers may change the maps they get).
  static Object? _copy(Object? value) {
    if (value is Map) {
      return <String, Object?>{
        for (final MapEntry<Object?, Object?> e in value.entries)
          '${e.key}': _copy(e.value),
      };
    }
    if (value is List) {
      return <Object?>[for (final Object? v in value) _copy(v)];
    }
    return value;
  }
}

/// The moment a response's body was received: for a `304` answered from the [EtagStore], the moment of the original
/// `200` (the entry, stamped by the first reader with its own clock); for a fresh `200`, [now] (and the stored entry is
/// stamped with it, so the next `304` returns the same moment in this clock).
DateTime receivedAtOf(Response<Object?> response, DateTime now) {
  final Object? replayed = response.extra[replayedFromExtra];
  if (replayed is ConditionalGetEntry) return replayed.receivedAt;
  final Object? stored = response.extra[storedAsExtra];
  if (stored is ConditionalGetEntry) stored.receivedAt = now;
  return now;
}

/// The response was answered from the [EtagStore] after a `304`.
bool isReplayed(Response<Object?> response) =>
    response.extra[replayedFromExtra] is ConditionalGetEntry;
