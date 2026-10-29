import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';

import 'p22.dart';

/// P6-11 format bloki (bootstrap `format`): profil, rejim, birinchi ekran va samarali modullar.
Map<String, Object?> formatBlock({
  String profile = 'osh_markazi',
  String label = 'Osh markazi',
  String mode = 'counter',
  String firstScreen = 'quick_counter',
  List<String> modules = const <String>[
    'quick_counter',
    'queue_numbers',
    'queue_calling',
    'batches',
    'batch_writeoff',
    'terminal_card_flow',
  ],
  bool serviceChargeZero = false,
}) => <String, Object?>{
  'profile': profile,
  'profile_label': label,
  'mode': mode,
  'first_screen': firstScreen,
  'modules': modules,
  'locks': <String, Object?>{'service_charge_zero': serviceChargeZero},
};

/// Tamaddixona: telefon rejimi, raqamsiz.
Map<String, Object?> tamaddixonaFormat() => formatBlock(
  profile: 'tamaddixona',
  label: 'Tamaddixona',
  modules: const <String>[
    'quick_counter',
    'phone_mode',
    'batches',
    'batch_writeoff',
    'terminal_card_flow',
  ],
);

/// Tez kassa menyusi (soxta backend'ga qo'shiladi): porsiya osh, lag'mon (majburiy "achchiqligi"), somsa, kola.
const int oshId = 11;
const int lagmonId = 12;
const int somsaId = 13;
const int colaId = 14;

void seedCounterMenu(FakePosState s) {
  s.categories.add(<String, Object?>{
    'id': 3,
    'name': 'Tez kassa',
    'parent_id': null,
    'sort_order': 0,
    'image_url': null,
  });
  s.products
    ..add(_product(oshId, 3, 'Osh porsiya', '35000.00', stationId: 1))
    ..add(
      _product(
        lagmonId,
        3,
        "Lag'mon",
        '30000.00',
        stationId: 1,
        modifierGroups: <Object?>[
          <String, Object?>{
            'id': 20,
            'uuid': 'mg-20',
            'name': 'Achchiqligi',
            'min_select': 1,
            'max_select': 1,
            'is_required': true,
            'sort_order': 0,
            'is_active': true,
            'modifiers': <Object?>[
              _modifier(21, 'Achchiq'),
              _modifier(22, 'Achchiqmas'),
            ],
          },
        ],
      ),
    )
    ..add(_product(somsaId, 3, 'Somsa', '8000.00'))
    ..add(_product(colaId, 1, 'Kola 0.5', '10000.00'));
}

String modifierUuid(int id) =>
    '00000000-0000-4000-8000-0000000002${id.toString().padLeft(2, '0')}';

Map<String, Object?> _modifier(int id, String name) => <String, Object?>{
  'id': id,
  'uuid': modifierUuid(id),
  'name': name,
  'price_delta': '0.00',
  'max_quantity': 1,
  'is_available': true,
  'unavailable_reason': null,
};

Map<String, Object?> _product(
  int id,
  int categoryId,
  String name,
  String price, {
  int? stationId,
  List<Object?> modifierGroups = const <Object?>[],
}) => <String, Object?>{
  'id': id,
  'uuid': 'product-$id',
  'store_id': FakePosState.storeId,
  'category_id': categoryId,
  'name': name,
  'description': null,
  'kind': 'dish',
  'sort_order': id,
  'batch_tracked': false,
  'is_available': true,
  'unavailable_reason': null,
  'station_id': stationId,
  'image_url': null,
  'variants': <Object?>[
    <String, Object?>{
      'id': id,
      'uuid': FakePosState.variantUuid(id),
      'product_id': id,
      'name': 'Porsiya',
      'price': price,
      'base_price': price,
      'stock': 1000,
      'unit': 'piece',
      'quantity_step': null,
      'min_quantity': null,
      'batch_consumption': '1.000',
      'unit_label': null,
      'is_available': true,
      'unavailable_reason': null,
    },
  ],
  'modifier_groups': modifierGroups,
};

/// P6-13 soxta backend qismi (kassa testlari uchun): bootstrap `queue` (kassa bloki), `POST /pos/orders` dagi
/// `queue_number`, buyurtmadagi `queue` va schyotdagi `ticket`, `GET /pos/queue`, `.../ready|call|handed` va
/// `queue.*` hodisalari. Raqam blokda bo'lmasa yoki band bo'lsa keyingisi beriladi (server qoidasi).
class FakeQueueBackend {
  FakeQueueBackend(this.p, {this.blocks = const <(int, int)>[(1, 100)]}) {
    _next = p.h.adapter.respond;
    p.h.adapter.respond = _respond;
    final FakeResponse? Function(FakeRequest r)? inner = p.state.extraRoutes;
    p.state.extraRoutes = (FakeRequest r) => _route(r) ?? inner?.call(r);
  }

  final P22 p;

  /// Kassaning bugungi bloklari (`start`, `end`).
  List<(int, int)> blocks;

  /// Bloklar kam qolganda `POST /pos/queue/blocks` beradigan keyingi blok.
  (int, int)? nextBlock;
  int reserveCalls = 0;

  /// Buyurtma uuid → navbat yozuvi.
  final Map<String, Map<String, Object?>> entries =
      <String, Map<String, Object?>>{};

  /// Mijoz ekraniga yuborilgan cheklar (`POST /pos/display/cart` tanasi).
  final List<Map<String, Object?>> displayed = <Map<String, Object?>>[];

  /// Ochishda kassa yuborgan raqamlar (tartib bilan).
  final List<int?> sentNumbers = <int?>[];

  FakeResponse? Function(RequestOptions options)? _next;

  FakePosState get state => p.state;
  String get businessDate => _date(state.now());

  int get _maxUsed => entries.values.fold(
    0,
    (int m, Map<String, Object?> e) =>
        (e['number']! as int) > m ? e['number']! as int : m,
  );

  Map<String, Object?> get queueState => <String, Object?>{
    'numbers': true,
    'calling': true,
    'business_date': businessDate,
    'block_size': 100,
    'low_water': 20,
    'blocks': <Object?>[
      for (final (int a, int b) in blocks)
        <String, Object?>{'start': a, 'end': b, 'next': a},
    ],
    'next_number': _maxUsed + 1,
  };

  FakeResponse? _respond(RequestOptions o) {
    final FakeResponse? earlier = _next?.call(o);
    if (earlier != null) return earlier;
    final String path = o.uri.path.replaceFirst('/api', '');
    final String? auth = (o.headers['Authorization'] as String?)?.replaceFirst(
      'Bearer ',
      '',
    );
    FakeResponse handle() => state.handle(
      o.method,
      o.uri.path,
      o.uri.queryParameters,
      o.data,
      auth,
      idempotencyKey: o.headers['Idempotency-Key'] as String?,
    );
    // P6-14: the till's cart for the customer display (terminal token; the fake core does not know the route)
    if (o.method == 'POST' && path == '/v1/pos/display/cart') {
      displayed.add(_decode(o.data));
      return const FakeResponse(200, <String, Object?>{
        'success': true,
        'data': <String, Object?>{'stored': true},
      });
    }
    if (o.method == 'GET' && path == '/v1/pos/bootstrap') {
      final FakeResponse r = handle();
      final Object? body = r.body;
      if (r.status == 200 && body is Map && body['data'] is Map) {
        (body['data'] as Map<Object?, Object?>)['queue'] = queueState;
      }
      return r;
    }
    if (o.method == 'POST' && path == '/v1/pos/orders') {
      final Map<String, Object?> data = _decode(o.data);
      final FakeResponse r = handle();
      final Object? body = r.body;
      if (r.status < 300 && body is Map && body['data'] is Map) {
        final Map<Object?, Object?> order =
            body['data'] as Map<Object?, Object?>;
        final String uuid = order['uuid']! as String;
        if (!entries.containsKey(uuid)) {
          final int? asked = (data['queue_number'] as num?)?.toInt();
          sentNumbers.add(asked);
          _assign(uuid, asked, '${order['channel'] ?? 'pos_takeaway'}');
        }
        order['queue'] = _queueOf(uuid);
        state.orders[uuid]?['queue'] = _queueOf(uuid);
      }
      _stampTickets();
      return r;
    }
    if (path.contains('/bills') || path.contains('/pay')) {
      final FakeResponse r = handle();
      _stampTickets();
      return r;
    }
    return null;
  }

  void _assign(String uuid, int? asked, String channel) {
    final Set<int> used = <int>{
      for (final Map<String, Object?> e in entries.values) e['number']! as int,
    };
    bool inBlock(int n) => blocks.any(((int, int) b) => n >= b.$1 && n <= b.$2);
    int n;
    if (asked != null && inBlock(asked) && !used.contains(asked)) {
      n = asked;
    } else {
      n = _maxUsed + 1;
    }
    entries[uuid] = <String, Object?>{
      'order_uuid': uuid,
      'number': n,
      'business_date': businessDate,
      'status': 'preparing',
      'channel': channel,
      'order_status': 'preparing',
      'ready_at': null,
      'called_at': null,
      'call_count': 0,
      'handed_at': null,
      'opened_at': _iso(state.now()),
    };
  }

  Map<String, Object?> _queueOf(String uuid) {
    final Map<String, Object?> e = entries[uuid]!;
    return <String, Object?>{
      'number': e['number'],
      'business_date': e['business_date'],
      'status': e['status'],
      'ready_at': e['ready_at'],
      'called_at': e['called_at'],
      'call_count': e['call_count'],
      'handed_at': e['handed_at'],
    };
  }

  void _stampTickets() {
    for (final MapEntry<String, Map<String, Object?>> e in entries.entries) {
      state.orders[e.key]?['queue'] = _queueOf(e.key);
      final String label = e.value['channel'] == 'pos_hall'
          ? 'Zalda'
          : 'Olib ketish';
      for (final Map<String, Object?> bill
          in p.backend.bills[e.key] ?? const <Map<String, Object?>>[]) {
        bill['ticket'] = <String, Object?>{
          'number': e.value['number'],
          'business_date': e.value['business_date'],
          'order_label': label,
          'message': "Buyurtmangiz tayyor bo'lganda raqamingiz ekranda chiqadi va chaqiriladi.",
        };
      }
    }
  }

  FakeResponse? _route(FakeRequest r) {
    if (r.method == 'GET' && r.path == '/v1/pos/queue') {
      List<Map<String, Object?>> of(String status) => <Map<String, Object?>>[
        for (final Map<String, Object?> e in entries.values)
          if (e['status'] == status) e,
      ];
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'data': <String, Object?>{
          'business_date': businessDate,
          'preparing': of('preparing'),
          'ready': of('ready'),
        },
      });
    }
    if (r.method == 'POST' && r.path == '/v1/pos/queue/blocks') {
      reserveCalls++;
      final (int, int)? b = nextBlock;
      if (b != null && !blocks.contains(b)) blocks = <(int, int)>[...blocks, b];
      return FakeResponse(200, <String, Object?>{
        'success': true,
        'data': queueState,
      });
    }
    final RegExpMatch? m = RegExp(
      r'^/v1/pos/orders/([^/]+)/(ready|call|handed)$',
    ).firstMatch(r.path);
    if (r.method == 'POST' && m != null) {
      final Map<String, Object?>? e = entries[m.group(1)];
      if (e == null) {
        return const FakeResponse(422, <String, Object?>{
          'success': false,
          'message': 'Navbatda emas.',
          'data': <String, Object?>{'reason': 'not_in_queue'},
        });
      }
      switch (m.group(2)) {
        case 'ready':
          markReady(m.group(1)!);
        case 'call':
          e
            ..['call_count'] = (e['call_count']! as int) + 1
            ..['called_at'] = _iso(state.now());
          _emit('queue.ready', e, called: true);
        case 'handed':
          e
            ..['status'] = 'handed'
            ..['handed_at'] = _iso(state.now());
          _emit('queue.handed', e);
      }
      return FakeResponse(200, <String, Object?>{'success': true, 'data': e});
    }
    return null;
  }

  /// Oshxona (KDS) oxirgi taomni "tayyor" bosdi.
  void markReady(String uuid) {
    final Map<String, Object?> e = entries[uuid]!;
    e
      ..['status'] = 'ready'
      ..['ready_at'] = _iso(state.now());
    _emit('queue.ready', e);
  }

  void _emit(String name, Map<String, Object?> e, {bool called = false}) {
    state.emit(name, <String, Object?>{
      'order': <String, Object?>{
        'uuid': e['order_uuid'],
        'status': state.orders[e['order_uuid']]?['status'],
        'branch_id': FakePosState.branchId,
      },
      'queue': <String, Object?>{
        'number': e['number'],
        'business_date': e['business_date'],
        'status': e['status'],
        'channel': e['channel'],
        'call_count': e['call_count'],
        'called': called,
      },
    });
  }

  /// Raqam bo'yicha buyurtma uuid'i.
  String uuidOf(int number) => entries.entries
      .firstWhere(
        (MapEntry<String, Map<String, Object?>> e) =>
            e.value['number'] == number,
      )
      .key;

  static Map<String, Object?> _decode(Object? body) {
    if (body is Map) return body.cast<String, Object?>();
    if (body is String && body.isNotEmpty) {
      return (jsonDecode(body) as Map<Object?, Object?>)
          .cast<String, Object?>();
    }
    return const <String, Object?>{};
  }

  static String _iso(DateTime d) => d.toUtc().toIso8601String();

  static String _date(DateTime d) {
    final DateTime t = d.toUtc().add(const Duration(hours: 5));
    return '${t.year}-${t.month.toString().padLeft(2, '0')}-${t.day.toString().padLeft(2, '0')}';
  }
}

/// [future] ni oxirigacha bajaradi, soxta soatni surib (Dio va sinxronlash taymerlari).
Future<T> drive<T>(WidgetTester tester, Future<T> future) async {
  var done = false;
  late T value;
  Object? error;
  unawaited(
    future.then(
      (T v) {
        value = v;
        done = true;
      },
      onError: (Object e) {
        error = e;
        done = true;
      },
    ),
  );
  for (var i = 0; i < 300 && !done; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  if (!done) throw StateError('did not finish');
  if (error != null) throw error!;
  await tester.pumpAndSettle();
  return value;
}
