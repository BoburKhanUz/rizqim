import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/vouchers/voucher_sell_dialog.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';

import 'support/harness.dart';
import 'support/p22.dart';
import 'support/wave4.dart';

// To'lqin 4 (B) kassa suratlari: QR kutilmoqda, QR to'landi (aralash: qolgani naqd), sertifikat bilan to'lash, korxona
// xodimi (limitlar), sertifikat ko'rinishi — planshet (1280×800) va telefon (390×844). Oddiy ishga tushirishda — test
// shrifti bilan golden'lar (`test/goldens/payments_*.png`). `PAYMENTS_SHOTS=<papka>` va `--update-goldens` bilan —
// haqiqiy Roboto shrifti bilan o'qiladigan suratlar shu papkaga.
// O'zgarishdan keyin: flutter test --update-goldens test/payments_goldens_test.dart
void main() {
  final String? shots = Platform.environment['PAYMENTS_SHOTS'];
  const Size tablet = Size(1280, 800);
  const Size phone = Size(390, 844);

  setUpAll(() async {
    if (shots == null) return;
    final String fonts =
        '${Platform.environment['FLUTTER_ROOT'] ?? '/opt/flutter'}/bin/cache/artifacts/material_fonts';
    final FontLoader roboto = FontLoader('Roboto');
    for (final String f in <String>[
      'Roboto-Regular.ttf',
      'Roboto-Medium.ttf',
      'Roboto-Bold.ttf',
      'Roboto-Black.ttf',
    ]) {
      roboto.addFont(
        Future<ByteData>.value(
          ByteData.sublistView(File('$fonts/$f').readAsBytesSync()),
        ),
      );
    }
    await roboto.load();
    final FontLoader icons = FontLoader('MaterialIcons')
      ..addFont(
        Future<ByteData>.value(
          ByteData.sublistView(
            File('$fonts/MaterialIcons-Regular.otf').readAsBytesSync(),
          ),
        ),
      );
    await icons.load();
  });

  Future<void> shot(WidgetTester tester, String name) async {
    if (shots != null) {
      for (final RenderParagraph r in tester.renderObjectList<RenderParagraph>(
        find.byType(RichText),
      )) {
        if (r.text.style?.fontFamily != null) continue;
        r.text = TextSpan(
          style: const TextStyle(fontFamily: 'Roboto'),
          children: <InlineSpan>[r.text],
        );
      }
      await tester.pump();
    }
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        shots == null
            ? 'goldens/payments_$name.png'
            : Uri.file('$shots/payments_$name.png'),
      ),
    );
  }

  BuildContext shell(WidgetTester tester) {
    final Finder f = find.byType(NavigationRail).evaluate().isNotEmpty
        ? find.byType(NavigationRail)
        : find.byType(NavigationBar);
    return tester.element(f.first);
  }

  /// Kassa ochiq, to'lov sahifasi (1.5 kg osh + 2 choy, xona: 509 000 so'm).
  Future<P22> payment(
    WidgetTester tester,
    Size size, {
    int tableId = 1,
    List<(int, String)>? lines,
  }) async {
    final P22 p = P22();
    p.state.format = wave4Format();
    p.state.openShift();
    p.backend
      ..useQr()
      ..useVouchers();
    acceptDisplay(p);
    final String uuid = lines == null
        ? p.state.seedOrder(tableId: tableId, guests: 3)
        : p.state.seedOrder(tableId: tableId, guests: 2, lines: lines);
    await pumpHome(tester, p.h, size: size);
    final PosOrder order = await settle(tester, p.h.data.orders.get(uuid));
    final PosBilling billing = await settle(
      tester,
      p.h.data.billing.createBills(uuid, version: order.version),
    );
    final PosOrder fresh = await settle(tester, p.h.data.orders.get(uuid));
    CashierRoutes.push<void>(
      shell(tester),
      PaymentPage(bill: billing.bills.single, order: fresh),
    ).ignore();
    await tester.pumpAndSettle();
    return p;
  }

  for (final (String device, Size size) in <(String, Size)>[
    ('tablet', tablet),
    ('phone', phone),
  ]) {
    testWidgets('$device: a QR waiting for the customer', (
      WidgetTester tester,
    ) async {
      await payment(tester, size);
      await pickTender(tester, 'QR');
      await tapKey(tester, 'qr-create');
      await shot(tester, '${device}_qr_waiting');
      await tester.pageBack();
      await tester.pumpAndSettle();
    });

    testWidgets('$device: a QR part paid, the rest in cash', (
      WidgetTester tester,
    ) async {
      final P22 p = await payment(tester, size);
      await pickTender(tester, 'QR');
      await enter(tester, 'qr-amount', '300000');
      await tapKey(tester, 'qr-create');
      p.backend.confirmQr('${p.backend.qrBackend!.qrs.values.single['uuid']}');
      await tester.pumpAndSettle();
      await shot(tester, '${device}_qr_paid');
    });

    testWidgets('$device: paying with a gift certificate', (
      WidgetTester tester,
    ) async {
      final P22 p = await payment(tester, size);
      p.backend.voucherBackend!.add(faceValue: '200000.00');
      await pickTender(tester, 'Sertifikat');
      await enter(tester, 'voucher-code', 'KQMA-CDEF-ACDE');
      await tapKey(tester, 'voucher-lookup');
      await shot(tester, '${device}_voucher_tender');
    });

    testWidgets('$device: a corporate member found by the talon code', (
      WidgetTester tester,
    ) async {
      final P22 p = await payment(
        tester,
        size,
        tableId: 11,
        lines: const <(int, String)>[(1, '2')],
      );
      final String account = p.backend.seedCorporate(
        name: 'Paxta Tekstil',
        company: '"Paxta Tekstil" AJ',
        monthlyLimit: '1000000.00',
      );
      p.backend.seedMember(
        account,
        name: 'Aziz Rahimov',
        code: 'PT-0001',
        phone: '+998901112233',
        dailyLimit: '40000.00',
      );
      await pickTender(tester, 'Nasiya');
      await tester.tap(find.widgetWithText(ChoiceChip, 'Korxona'));
      await tester.pumpAndSettle();
      await enter(tester, 'member-code', 'PT-0001');
      await tapKey(tester, 'member-lookup');
      await shot(tester, '${device}_corporate_lookup');
    });

    testWidgets('$device: the certificate after the sale', (
      WidgetTester tester,
    ) async {
      final P22 p = P22();
      p.state.format = wave4Format();
      p.state.openShift();
      p.backend.useVouchers();
      await pumpHome(tester, p.h, size: size);
      final PosVoucher v = await settle(
        tester,
        p.h.data.vouchers.sell(
          const PosVoucherSale(
            faceValue: PosMoney.som(200000),
            tender: PosTender.cash,
            buyerName: 'Dilnoza',
          ),
        ),
      );
      showVoucherPreview(shell(tester), v).ignore();
      await tester.pumpAndSettle();
      await shot(tester, '${device}_certificate_preview');
    });
  }
}
