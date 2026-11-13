import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';

import 'harness.dart';
import 'p22.dart';

/// To'lqin 4 (B) testlari uchun: choyxona formati va yangi modullar (QR, sertifikat, korxona, mijoz ekrani).
Map<String, Object?> wave4Format({
  bool qr = true,
  bool vouchers = true,
  bool corporate = true,
  bool customerAccounts = true,
  bool display = true,
}) => <String, Object?>{
  'profile': 'choyxona',
  'profile_label': 'Choyxona',
  'mode': 'hybrid',
  'first_screen': 'hall_map',
  'modules': <String>[
    'quick_counter',
    'batches',
    'rooms',
    'room_fee',
    'guest_items',
    'bookings',
    if (customerAccounts) 'customer_accounts',
    'service_charge',
    'kds',
    if (display) 'customer_display',
    if (qr) 'qr_payments',
    if (vouchers) 'vouchers',
    if (corporate) 'corporate_accounts',
  ],
  'locks': <String, Object?>{'service_charge_zero': false},
};

/// Kassa, smena ochiq, to'lov sahifasi ochiq: 1.5 kg osh + 2 choy, 3 mehmon, xona — 509 000 so'm.
Future<String> openPayment(
  WidgetTester tester,
  P22 p, {
  String? name,
  Size size = const Size(1280, 800),
  int guests = 3,
  int tableId = 1,
  List<(int, String)>? lines,
}) async {
  final String uuid = lines == null
      ? p.state.seedOrder(tableId: tableId, guests: guests)
      : p.state.seedOrder(tableId: tableId, guests: guests, lines: lines);
  if (name == null) {
    await pumpHome(tester, p.h, size: size);
  } else {
    await pumpHome(tester, p.h, name: name, size: size);
  }
  await openBilling(tester, uuid);
  await tapButton(tester, 'Schyot tuzish');
  await tapText(tester, "To'lash: ", contains: true);
  expect(find.byType(PaymentPage), findsOneWidget);
  return uuid;
}

/// To'lov sahifasidagi tur (ChoiceChip).
Future<void> pickTender(WidgetTester tester, String label) async {
  final Finder chip = find.widgetWithText(ChoiceChip, label).first;
  await tester.ensureVisible(chip);
  await tester.pumpAndSettle();
  await tester.tap(chip);
  await tester.pumpAndSettle();
}

/// Mijoz ekrani yuborishi (`POST /pos/display/cart`, terminal kaliti bilan) — soxta serverda yo'q: qabul qilinadi.
void acceptDisplay(P22 p) {
  p.h.adapter.respond = (RequestOptions o) =>
      o.method == 'POST' && o.uri.path.endsWith('/pos/display/cart')
      ? const FakeResponse(200, <String, Object?>{
          'success': true,
          'message': '',
          'data': <String, Object?>{'stored': true},
        })
      : null;
}
