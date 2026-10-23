// Ofitsiant ilovasini Linux'da backend'siz ishga tushirish va suratga olish uchun soxta backend: Botir (PIN 1357)
// ofitsiantning ikkita ochiq xonasi (biridan taom tayyor), Aziz Qodirovning (PIN 1111) bitta xonasi, qolgani bo'sh.
//
//   cd pos/apps/waiter && dart run tool/demo_server.dart --port 8789
//   flutter run -d linux --dart-define=RIZQIM_API_URL=http://127.0.0.1:8789/api \
//     --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8789
//
// Soxta backend ofitsiant qoidasini bilmaydi: bu yerda u qo'shiladi (boshqa ofitsiantning buyurtmasi tasdiqsiz 403
// `approval_required`). Ulash kodi — istalgan 8 belgi. Menejer Malika — PIN 5926. Boshqarish:
// `POST /__control/offline?on=1|0` (aloqa uzilishi).
import 'dart:io';

import 'package:pos_data/testing.dart';

const int _botir = 4;
const int _aziz = 5;

Future<void> main(List<String> args) async {
  final int i = args.indexOf('--port');
  final int port = i >= 0 && i + 1 < args.length
      ? int.parse(args[i + 1])
      : 8789;
  final FakePosServer server = await FakePosServer.start(port: port)
    ..verbose = true;
  _seed(server.state);
  stdout.writeln(
    'Ofitsiant demo backend: ${server.apiBaseUrl} (Reverb: ${server.reverbUrl}/app/${FakePosState.realtimeKey})',
  );
}

void _seed(FakePosState s) {
  s.staff.add(<String, Object?>{
    'id': _aziz,
    'name': 'Aziz Qodirov',
    'role': 'waiter',
    'staff_code': '1005',
  });
  s.pins[_aziz] = '1111';
  s.openShift();

  // Buyurtmalar haqiqiy yo'llar bilan ochiladi (jami va versiyalar to'g'ri bo'lishi uchun)
  s.handle(
    'POST',
    '/api/v1/devices/pair',
    const <String, String>{},
    <String, Object?>{'code': 'ABCD-EFGH'},
    null,
  );
  final String botir = _signIn(s, _botir);
  final String room1 = _open(s, botir, tableId: 1, guests: 8);
  _round(s, botir, room1, <Map<String, Object?>>[
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(3),
      'quantity': '3.000',
    },
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(2),
      'quantity': '8',
    },
  ]);
  _round(s, botir, room1, <Map<String, Object?>>[
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(4),
      'quantity': '10',
      'modifiers': <Object?>[
        <String, Object?>{
          'id': '00000000-0000-4000-8000-0000000000a1',
          'quantity': 1,
        },
      ],
      'note': 'yaxshi pishgan',
    },
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(1),
      'quantity': '4',
    },
  ]);
  _backdate(s, room1, const Duration(minutes: 135));
  final List<Map<String, Object?>> items = _items(s, room1);
  // Shashlik tayyor, osh va choy hali oshxonada
  items[2]['kitchen_status'] = 'ready';
  items[0]['kitchen_status'] = 'served';

  final String tapchan = _open(s, botir, tableId: 12, guests: 3);
  _round(s, botir, tapchan, <Map<String, Object?>>[
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(1),
      'quantity': '1',
    },
  ], send: false);
  _backdate(s, tapchan, const Duration(minutes: 12));

  final String aziz = _signIn(s, _aziz);
  final String room2 = _open(s, aziz, tableId: 2, guests: 6);
  _round(s, aziz, room2, <Map<String, Object?>>[
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(3),
      'quantity': '2.000',
    },
    <String, Object?>{
      'variant_id': FakePosState.variantUuid(1),
      'quantity': '3',
    },
  ]);
  _backdate(s, room2, const Duration(minutes: 50));

  // Shashlik uchun majburiy guruh (suratlarda tanlov qoidalari ko'rinsin)
  final Map<String, Object?> shashlik = s.products.firstWhere(
    (Map<String, Object?> p) => p['id'] == 4,
  );
  (shashlik['modifier_groups']! as List<Object?>).insert(0, <String, Object?>{
    'id': 2,
    'uuid': 'mg-2',
    'name': 'Pishirish darajasi',
    'min_select': 1,
    'max_select': 1,
    'is_required': true,
    'sort_order': -1,
    'is_active': true,
    'modifiers': <Object?>[
      for (final (int, String, String) m in <(int, String, String)>[
        (5, 'Yaxshi pishgan', '00000000-0000-4000-8000-0000000000b1'),
        (6, 'Yarim pishgan', '00000000-0000-4000-8000-0000000000b2'),
      ])
        <String, Object?>{
          'id': m.$1,
          'uuid': m.$3,
          'name': m.$2,
          'price_delta': '0.00',
          'max_quantity': 1,
          'is_available': true,
        },
    ],
  });
  (shashlik['modifier_groups']! as List<Object?>).add(<String, Object?>{
    'id': 3,
    'uuid': 'mg-3',
    'name': 'Sous',
    'min_select': 0,
    'max_select': 2,
    'is_required': false,
    'sort_order': 5,
    'is_active': true,
    'modifiers': <Object?>[
      for (final (int, String, String) m in <(int, String, String)>[
        (7, 'Achchiq sous', '00000000-0000-4000-8000-0000000000c1'),
        (8, 'Qatiq', '00000000-0000-4000-8000-0000000000c2'),
        (9, 'Pomidor sousi', '00000000-0000-4000-8000-0000000000c3'),
      ])
        <String, Object?>{
          'id': m.$1,
          'uuid': m.$3,
          'name': m.$2,
          'price_delta': '1000.00',
          'max_quantity': 1,
          'is_available': true,
        },
    ],
  });

  // Qurilma ulanmagan holatga qaytadi: ilova o'zi ulanadi
  s.paired = false;

  // Ofitsiant qoidasi: boshqa ofitsiantning buyurtmasi menejer tasdig'isiz o'zgarmaydi
  s.extraRoutes = (FakeRequest r) {
    final RegExpMatch? m = RegExp(
      r'^/v1/pos/orders/([^/]+)/(rounds|send|guests|move|items/.+)$',
    ).firstMatch(r.path);
    if (r.method != 'POST' || m == null || r.member['role'] != 'waiter') {
      return null;
    }
    final Object? owner =
        (s.orders[m.group(1)]?['waiter'] as Map<Object?, Object?>?)?['id'];
    if (owner == null ||
        owner == r.member['id'] ||
        r.data['approval_token'] != null) {
      return null;
    }
    return const FakeResponse(403, <String, Object?>{
      'success': false,
      'message': "Bu buyurtma boshqa ofitsiantniki: uni o'zgartirish uchun menejer tasdig'i kerak.",
      'data': <String, Object?>{
        'reason': 'approval_required',
        'permission': 'orders.manage',
      },
    });
  };
}

String _signIn(FakePosState s, int userId) {
  final FakeResponse r = s.handle(
    'POST',
    '/api/v1/devices/${FakePosState.deviceUuid}/staff-session',
    const <String, String>{},
    <String, Object?>{'user_id': userId, 'pin': s.pins[userId]},
    FakePosState.terminalToken,
  );
  final Map<Object?, Object?> body = r.body! as Map<Object?, Object?>;
  final Map<Object?, Object?> data = body['data']! as Map<Object?, Object?>;
  return '${data['token']}';
}

String _open(
  FakePosState s,
  String token, {
  required int tableId,
  int guests = 2,
}) {
  final FakeResponse r = s.handle(
    'POST',
    '/api/v1/pos/orders',
    const <String, String>{},
    <String, Object?>{
      'channel': 'pos_hall',
      'table_id': tableId,
      'guests_count': guests,
    },
    token,
  );
  final Map<Object?, Object?> body = r.body! as Map<Object?, Object?>;
  return '${(body['data']! as Map<Object?, Object?>)['uuid']}';
}

void _round(
  FakePosState s,
  String token,
  String uuid,
  List<Map<String, Object?>> items, {
  bool send = true,
}) {
  s.handle(
    'POST',
    '/api/v1/pos/orders/$uuid/rounds',
    const <String, String>{},
    <String, Object?>{
      'version': s.orders[uuid]!['version'],
      'items': items,
      'send': send,
      'fire_mode': send ? 'now' : 'hold',
    },
    token,
  );
}

List<Map<String, Object?>> _items(FakePosState s, String uuid) =>
    (s.orders[uuid]!['items']! as List<Object?>).cast<Map<String, Object?>>();

void _backdate(FakePosState s, String uuid, Duration age) {
  s.orders[uuid]!['opened_at'] = s
      .now()
      .subtract(age)
      .toUtc()
      .toIso8601String();
}
