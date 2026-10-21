import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_kds/src/realtime/branch_realtime.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/sound/ticket_sound.dart';

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
    services = KdsServices(
      client: client,
      settings: this.settings,
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
  late final KdsServices services;

  FakeKitchenState get state => backend.state;
}
