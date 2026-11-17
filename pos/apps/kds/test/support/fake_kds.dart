import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_kds/src/realtime/branch_realtime.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/sound/ticket_sound.dart';
import 'package:rizqim_kds/src/stations/station_selection.dart';

import 'fake_kitchen_state.dart';
import 'fake_realtime.dart';

/// Dio adapteri: so'rovlarga [FakeKitchenState] javob beradi.
class FakeKds implements HttpClientAdapter {
  FakeKds({DateTime Function()? clock})
    : state = FakeKitchenState(clock: clock);

  final FakeKitchenState state;
  final List<RequestOptions> requests = <RequestOptions>[];

  /// Hamma so'rov tarmoq xatosi bilan tugaydi.
  bool offline = false;

  /// M2: conditional GETs (`ETag`, `304`).
  bool etags = true;

  /// `304` answers given.
  int notModified = 0;

  static String _fnv(String s) {
    var h = 0xcbf29ce484222325;
    for (final int c in utf8.encode(s)) {
      h ^= c;
      h = (h * 0x100000001b3) & 0x7fffffffffffffff;
    }
    return h.toRadixString(36);
  }

  /// Berilsa, `/staff/` ostidagi POST so'rovlar shu tugaguncha javob kutadi (optimistik holatni ko'rish uchun).
  Completer<void>? hold;

  /// Berilsa va `null` qaytarmasa, shu javob ishlatiladi.
  FakeResponse? Function(RequestOptions options)? respond;

  Iterable<RequestOptions> to(String method, Pattern path) => requests.where(
    (RequestOptions r) => r.method == method && r.uri.path.contains(path),
  );

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (offline) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'offline',
      );
    }
    FakeResponse? response = respond?.call(options);
    final Completer<void>? gate = hold;
    if (response == null &&
        gate != null &&
        options.method == 'POST' &&
        options.uri.path.contains('/staff/')) {
      await gate.future;
    }
    final String? auth = options.headers['Authorization'] as String?;
    response ??= state.handle(
      options.method,
      options.uri.path,
      options.uri.queryParameters,
      options.data,
      auth?.replaceFirst('Bearer ', ''),
    );
    // M2: the polled GETs carry X-Revision and an ETag bound to the token and the body; a matching If-None-Match is
    // a 304 with no body (as the server)
    if (etags &&
        options.method == 'GET' &&
        response.status == 200 &&
        isConditionalGetPath(options.uri.path)) {
      final String encoded = jsonEncode(response.body);
      final String tag =
          '"k.${_fnv('$auth|${options.uri}|${state.revision}|$encoded')}"';
      final Map<String, String> headers = <String, String>{
        ...response.headers,
        'ETag': tag,
        'X-Revision': '${state.revision}',
        'X-Revision-Scope': 'branch:${FakeKitchenState.branchId}',
      };
      if (options.headers['If-None-Match'] == tag) {
        notModified++;
        return ResponseBody.fromString(
          '',
          304,
          headers: <String, List<String>>{
            'etag': <String>[tag],
          },
        );
      }
      response = FakeResponse(response.status, response.body, headers: headers);
    }
    return ResponseBody.fromString(
      jsonEncode(response.body),
      response.status,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
        for (final MapEntry<String, String> h in response.headers.entries)
          h.key.toLowerCase(): <String>[h.value],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Testdagi butun ilova: soxta backend, soxta Reverb, xotiradagi omborlar, qo'lda suriladigan soat.
class KdsHarness {
  KdsHarness({
    bool realtime = true,
    Map<String, Object?>? settings,
    Map<String, Object?>? stationValues,
    Duration pollInterval = const Duration(seconds: 15),
  }) {
    backend = FakeKds(clock: () => now);
    hub = FakeRealtimeHub();
    backend.state.onEvent = hub.broadcast;
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: store,
      dio: Dio()..httpClientAdapter = backend,
      clock: () => now,
    );
    this.settings = KdsSettingsController(MemorySettingsStore(settings));
    stationStore = MemorySettingsStore(
      stationValues == null ? null : Map<String, Object?>.of(stationValues),
    );
    stations = StationSelectionController(
      stationStore,
      StationSelection.fromMap(stationValues ?? const <String, Object?>{}),
    );
    services = KdsServices(
      client: client,
      settings: this.settings,
      stations: stations,
      sound: sound,
      clock: () => now,
      pollInterval: pollInterval,
      realtimeFactory: realtime
          ? (RizqimClient c, PairedTerminal t) => BranchRealtime.create(
              client: c,
              terminal: t,
              apiBaseUrl: 'https://api.test/api',
              appKey: 'kds-key',
              connector: hub.connect,
              // The tests below time the old 1-2-4 s backoff; the 0-10 s first spread is tested in rizqim_api
              backoff: const Backoff(),
            )
          : null,
    );
  }

  DateTime now = DateTime(2026, 10, 21, 12);
  final MemoryKeyValueStore store = MemoryKeyValueStore();
  final SilentTicketSound sound = SilentTicketSound();
  late final FakeKds backend;
  late final FakeRealtimeHub hub;
  late final RizqimClient client;
  late final KdsSettingsController settings;
  late final MemorySettingsStore stationStore;
  late final StationSelectionController stations;
  late final KdsServices services;

  FakeKitchenState get state => backend.state;
}
