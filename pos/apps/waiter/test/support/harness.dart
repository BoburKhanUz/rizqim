import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/app.dart';
import 'package:rizqim_waiter/src/services.dart';

/// Testdagi butun ofitsiant ilovasi: soxta backend, soxta Reverb, xotiradagi ombor, qo'lda suriladigan soat.
class WaiterHarness {
  WaiterHarness() {
    adapter = FakePosAdapter(clock: () => now);
    hub = FakePosRealtimeHub();
    adapter.state.onEvent = hub.broadcast;
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    services = WaiterServices(
      client: client,
      clock: () => now,
      realtimeFactory: (RizqimClient c, PairedTerminal t) => PosRealtime.create(
        client: c,
        terminal: t,
        apiBaseUrl: 'https://api.test/api',
        appKey: FakePosState.realtimeKey,
        connector: hub.connect,
      ),
    );
  }

  DateTime now = DateTime.utc(2026, 10, 22, 13);
  late final FakePosAdapter adapter;
  late final FakePosRealtimeHub hub;
  late final RizqimClient client;
  late final WaiterServices services;

  FakePosState get state => adapter.state;
}

Future<T> settle<T>(WidgetTester tester, Future<T> future) async {
  await tester.pumpAndSettle();
  return future;
}

/// Telefon (portret).
Future<void> pumpWaiter(
  WidgetTester tester,
  WaiterHarness h, {
  Size size = const Size(412, 860),
}) async {
  tester.view
    ..physicalSize = size
    ..devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(WaiterApp(services: h.services));
  await tester.pumpAndSettle();
}

Future<void> typePin(WidgetTester tester, String pin) async {
  for (final String d in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(d).last);
    await tester.pump();
  }
  await tester.tap(find.bySemanticsLabel('Kirish').last);
  await tester.pumpAndSettle();
}

/// [typePin] ning `pumpAndSettle`siz varianti: ostida to'xtovsiz aylanadigan yuklanish belgisi bo'lsa.
Future<void> typePinBriefly(WidgetTester tester, String pin) async {
  for (final String d in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(d).last);
    await tester.pump();
  }
  await tester.tap(find.bySemanticsLabel('Kirish').last);
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 250));
  }
}

/// Ulangan, [name] (standart — ofitsiant) PIN bilan kirgan.
Future<void> pumpHome(
  WidgetTester tester,
  WaiterHarness h, {
  String name = 'Botir Aliyev',
}) async {
  await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
  await pumpWaiter(tester, h);
  await tester.tap(find.text(name));
  await tester.pumpAndSettle();
  final int id =
      h.state.staff.firstWhere(
            (Map<String, Object?> s) => s['name'] == name,
          )['id']!
          as int;
  await typePin(tester, h.state.pins[id]!);
}

Finder buttonLabeled(String label) => find.widgetWithText(RzButton, label);

/// Ikkinchi ofitsiant (boshqa ofitsiantning buyurtmasi uchun).
const int otherWaiterId = 5;
const String otherWaiterName = 'Aziz Qodirov';

/// Soxta backend'ga ikkinchi ofitsiantni qo'shadi.
void addOtherWaiter(WaiterHarness h) {
  h.state.staff.add(<String, Object?>{
    'id': otherWaiterId,
    'name': otherWaiterName,
    'role': 'waiter',
    'staff_code': '1005',
  });
  h.state.pins[otherWaiterId] = '1111';
}

/// [tableId] dagi ochiq buyurtma [waiterId] (standart — Botir, kirgan ofitsiant) nomida. [lines] — (variant, miqdor).
String seedWaiterOrder(
  WaiterHarness h, {
  required int tableId,
  int waiterId = 4,
  String waiterName = 'Botir Aliyev',
  int guests = 4,
  List<(int, String)> lines = const <(int, String)>[(3, '1.500'), (1, '2')],
  bool sent = true,
}) {
  final String uuid = h.state.seedOrder(
    tableId: tableId,
    guests: guests,
    lines: lines,
    sent: sent,
  );
  h.state.orders[uuid]!['waiter'] = <String, Object?>{
    'id': waiterId,
    'name': waiterName,
  };
  return uuid;
}

/// Buyurtmadagi [index]-qatorni oshpaz "tayyor" qildi (KDS shunday qiladi; ilova buni faqat ko'radi).
void markLineReady(WaiterHarness h, String orderUuid, int index) {
  final List<Object?> items =
      h.state.orders[orderUuid]!['items']! as List<Object?>;
  (items[index]! as Map<String, Object?>)['kitchen_status'] = 'ready';
}

/// Haqiqiy backend'dagi ofitsiant qoidasi (soxta backend uni bilmaydi): boshqa ofitsiantning buyurtmasini
/// o'zgartirish tasdiqsiz 403 `approval_required` oladi.
void enforceWaiterRule(WaiterHarness h) {
  h.adapter.respond = (RequestOptions o) {
    if (o.method != 'POST') return null;
    final RegExpMatch? m = RegExp(
      r'/v1/pos/orders/([^/]+)/(rounds|send|guests|move|items/.+)$',
    ).firstMatch(o.uri.path);
    if (m == null) return null;
    final Map<String, Object?>? order = h.state.orders[m.group(1)];
    final Object? owner = (order?['waiter'] as Map<Object?, Object?>?)?['id'];
    final String? token = (o.headers['Authorization'] as String?)?.replaceFirst(
      'Bearer ',
      '',
    );
    final Map<String, Object?>? actor = h.state.staffOf(token);
    final Object? body = o.data;
    final Map<Object?, Object?> data = body is Map
        ? body
        : (body is String && body.isNotEmpty
              ? jsonDecode(body) as Map<Object?, Object?>
              : <Object?, Object?>{});
    if (actor == null ||
        actor['role'] != 'waiter' ||
        owner == null ||
        owner == actor['id'] ||
        data['approval_token'] != null) {
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

/// Tanlangan matnli tugma (`RzButton`) bosiladi.
Future<void> tapButton(WidgetTester tester, String label) async {
  final Finder f = buttonLabeled(label);
  await tester.ensureVisible(f.first);
  await tester.tap(f.first);
  await tester.pumpAndSettle();
}

/// [path] ga kelgan so'rovlarning tanasi (JSON xarita).
List<Map<String, Object?>> bodies(
  WaiterHarness h,
  String method,
  Pattern path,
) => <Map<String, Object?>>[
  for (final RequestOptions r in h.adapter.to(method, path))
    if (r.data is Map)
      (r.data! as Map<Object?, Object?>).cast<String, Object?>()
    else if (r.data is String && (r.data! as String).isNotEmpty)
      (jsonDecode(r.data! as String) as Map<Object?, Object?>)
          .cast<String, Object?>(),
];
