import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_customer_display/src/app.dart';
import 'package:rizqim_customer_display/src/settings/display_settings.dart';
import 'package:rizqim_customer_display/src/sound/ready_sound.dart';

/// Soxta mijoz ekrani serveri: `GET /pos/display/state` va `/queue` (terminal tokeni bilan), hodisalar soxta Reverb orqali.
class FakeDisplayBackend {
  FakeDisplayBackend(this.now, this.hub);

  final DateTime Function() now;
  final FakePosRealtimeHub hub;

  /// Ekranni qaysi modullar yoqadi.
  bool customerDisplay = true;
  bool queueCalling = true;

  /// `branch_required` kabi rad etish (state va queue ikkalasiga).
  FakeResponse? refusal;

  String? tillUuid;
  String storeName = 'Osh markazi';

  /// Kassa yuborgan chek (server `display.cart` va `GET state` da shuni beradi); `null` — hali yo'q.
  Map<String, Object?>? cart;
  DateTime? cartUpdatedAt;
  int _seq = 0;

  final List<Map<String, Object?>> preparing = <Map<String, Object?>>[];
  final List<Map<String, Object?>> ready = <Map<String, Object?>>[];

  int stateCalls = 0;
  int queueCalls = 0;

  FakeResponse? handle(RequestOptions o) {
    final String path = o.uri.path;
    if (path != '/api/v1/pos/display/state' &&
        path != '/api/v1/pos/display/queue') {
      return null;
    }
    if (o.headers['Authorization'] != 'Bearer ${FakePosState.terminalToken}') {
      return const FakeResponse(401, <String, Object?>{
        'message': 'Unauthenticated.',
      });
    }
    if (refusal != null) return refusal;
    if (path.endsWith('/state')) {
      stateCalls++;
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'message': '',
        'data': <String, Object?>{
          'branch': <String, Object?>{'id': 1, 'name': 'Chilonzor'},
          'store': <String, Object?>{'id': 1, 'name': storeName},
          'device': <String, Object?>{
            'uuid': 'd1',
            'type': 'display',
            'name': 'Mijoz ekrani',
          },
          'till_uuid': tillUuid,
          'modules': <String, Object?>{
            'customer_display': customerDisplay,
            'queue_calling': queueCalling,
          },
          'server_time': now().toUtc().toIso8601String(),
          'cart': customerDisplay ? cart : null,
        },
      });
    }
    queueCalls++;
    if (!queueCalling) {
      return const FakeResponse(403, <String, Object?>{
        'success': false,
        'message': 'Raqamni chaqirish moduli o\'chirilgan.',
        'code': 'module_disabled',
        'module': 'queue_calling',
        'data': <String, Object?>{
          'reason': 'module_disabled',
          'module': 'queue_calling',
        },
      });
    }
    return FakeResponse(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': <String, Object?>{
        'business_date': '2026-10-22',
        'preparing': preparing,
        'ready': ready,
      },
    });
  }

  // --- kassa tomoni --------------------------------------------------------------------------------------------

  static Map<String, Object?> line(
    String name, {
    String qty = '1',
    String? unit,
    required String price,
    String? total,
    List<String> modifiers = const <String>[],
    String? note,
  }) => <String, Object?>{
    'name': name,
    'qty': qty,
    'unit': unit,
    'price': price,
    'total': total ?? price,
    'modifiers': modifiers,
    'note': note,
  };

  /// Kassa chekni yubordi: server saqlaydi va `display.cart` hodisasini yuboradi.
  void publish({
    String state = 'cart',
    List<Map<String, Object?>> lines = const <Map<String, Object?>>[],
    String total = '0.00',
    String discount = '0.00',
    String? subtotal,
    int? queueNumber,
    Map<String, Object?>? payment,
    String terminal = 'till-1',
  }) {
    _seq++;
    cartUpdatedAt = now();
    cart = <String, Object?>{
      'terminal': terminal,
      'seq': _seq,
      'session': 'run-1',
      'state': state,
      'lines': lines,
      'subtotal': subtotal ?? total,
      'discount': discount,
      'total': total,
      'queue_number': queueNumber,
      'payment': payment,
      'updated_at': cartUpdatedAt!.toUtc().toIso8601String(),
    };
    hub.broadcast('display.cart', <String, Object?>{
      'terminal': terminal,
      'seq': _seq,
      'state': state,
      'branch_id': 1,
      'store_id': 1,
    });
  }

  static Map<String, Object?> entry(
    int number,
    String status, {
    DateTime? readyAt,
    int calls = 0,
  }) => <String, Object?>{
    'order_uuid': 'o$number',
    'number': number,
    'business_date': '2026-10-22',
    'status': status,
    'channel': 'pos_takeaway',
    'order_status': 'delivered',
    'ready_at': readyAt?.toUtc().toIso8601String(),
    'called_at': null,
    'call_count': calls,
    'handed_at': null,
    'opened_at': null,
  };

  Map<String, Object?> _queueEvent(
    int number,
    String status, {
    bool called = false,
    int calls = 0,
  }) => <String, Object?>{
    'order': <String, Object?>{
      'uuid': 'o$number',
      'status': 'delivered',
      'store_id': 1,
      'branch_id': 1,
      'courier_id': null,
      'updated_at': now().toUtc().toIso8601String(),
    },
    'queue': <String, Object?>{
      'number': number,
      'business_date': '2026-10-22',
      'status': status,
      'channel': 'pos_takeaway',
      'ready_at': now().toUtc().toIso8601String(),
      'called_at': called ? now().toUtc().toIso8601String() : null,
      'call_count': calls,
      'handed_at': null,
      'called': called,
    },
  };

  /// Yangi raqam oshxonaga tushdi.
  void addPreparing(int number) {
    preparing.add(entry(number, 'preparing'));
    hub.broadcast('queue.preparing', _queueEvent(number, 'preparing'));
  }

  /// Raqam tayyor bo'ldi (oshxona yoki kassa): server taxtani yangilaydi, hodisa yuboradi.
  void markReady(int number) {
    preparing.removeWhere((Map<String, Object?> e) => e['number'] == number);
    ready.removeWhere((Map<String, Object?> e) => e['number'] == number);
    ready.add(entry(number, 'ready', readyAt: now()));
    hub.broadcast('queue.ready', _queueEvent(number, 'ready'));
  }

  /// Kassa raqamni qayta chaqirdi.
  void callAgain(int number) {
    final Map<String, Object?> e = ready.firstWhere(
      (Map<String, Object?> e) => e['number'] == number,
    );
    final int calls = (e['call_count']! as int) + 1;
    e['call_count'] = calls;
    hub.broadcast(
      'queue.ready',
      _queueEvent(number, 'ready', called: true, calls: calls),
    );
  }

  /// Mijoz oldi: raqam taxtadan ketdi.
  void hand(int number) {
    ready.removeWhere((Map<String, Object?> e) => e['number'] == number);
    preparing.removeWhere((Map<String, Object?> e) => e['number'] == number);
    hub.broadcast('queue.handed', _queueEvent(number, 'handed'));
  }
}

/// Butun ilova soxta server ustida: [FakePosAdapter] → [RizqimClient] → [DisplayApp].
class DisplayHarness {
  DisplayHarness({
    bool realtime = true,
    Map<String, Object?>? settings,
    this.pollInterval = const Duration(seconds: 5),
    Duration paidHold = const Duration(seconds: 8),
    Duration staleCart = const Duration(minutes: 30),
    DateTime? start,
  }) : now = start ?? DateTime.utc(2026, 10, 22, 13) {
    hub = FakePosRealtimeHub();
    backend = FakeDisplayBackend(() => now, hub);
    adapter = FakePosAdapter(clock: () => now)
      ..respond = (RequestOptions o) => backend.handle(o);
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    this.settings = DisplaySettingsController(
      MemorySettingsStore(settings),
      DisplaySettings.fromMap(settings ?? const <String, Object?>{}),
    );
    services = DisplayServices(
      client: client,
      settings: this.settings,
      sound: sound,
      clock: () => now,
      pollInterval: pollInterval,
      paidHold: paidHold,
      staleCart: staleCart,
      realtimeFactory: realtime
          ? (RizqimClient c, PairedTerminal t) => PosRealtime.create(
              client: c,
              terminal: t,
              apiBaseUrl: 'https://api.test/api',
              appKey: FakePosState.realtimeKey,
              clientName: 'rizqim-display',
              connector: hub.connect,
            )
          : null,
    );
  }

  DateTime now;
  final Duration pollInterval;
  late final FakePosRealtimeHub hub;
  late final FakeDisplayBackend backend;
  late final FakePosAdapter adapter;
  late final RizqimClient client;
  late final DisplaySettingsController settings;
  late final DisplayServices services;
  final SilentReadySound sound = SilentReadySound();

  /// Mijoz ekrani ulangan (kassa va ekran bir backend'da); ilovani ochadi.
  Future<void> pump(
    WidgetTester tester, {
    Size size = const Size(1280, 800),
    bool paired = true,
    Widget Function(Widget app)? wrap,
  }) async {
    tester.view
      ..physicalSize = size
      ..devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    // Dio taymerlari faqat soxta soat surilganda ishlaydi: kutishdan oldin pumpAndSettle
    if (paired) {
      final Future<Object?> pairing = client.auth.pairTerminal('ABCD-EFGH');
      await tester.pumpAndSettle();
      await pairing;
    }
    final Widget app = DisplayApp(services: services);
    await tester.pumpWidget(wrap == null ? app : wrap(app));
    await tester.pump();
    await advance(tester, const Duration(milliseconds: 700));
  }

  /// Soatni [d] ga suradi (qadam-qadam: taymerlar ishlaydi).
  Future<void> advance(
    WidgetTester tester,
    Duration d, {
    Duration step = const Duration(milliseconds: 100),
  }) async {
    var left = d;
    while (left > Duration.zero) {
      final Duration s = left > step ? step : left;
      now = now.add(s);
      await tester.pump(s);
      left -= s;
    }
  }
}

Finder text(String t) => find.text(t);
