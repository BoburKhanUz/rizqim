import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'layout_test.dart' show expectFits;
import 'support/fixtures.dart';
import 'support/golden_text.dart';

/// P6-41/P6-43 (wave 4 stage B): the gift certificate sheet, the payment QR on the pre-check, and the X/Z sections for
/// certificates and the QR providers.
void main() {
  GiftCertificate certificate({bool copy = false, DateTime? until}) =>
      GiftCertificate(
        header: header,
        code: 'KQMA-CDEF-ACDE',
        qr: 'KQMA-CDEF-ACDE',
        value: const PosMoney.som(200000),
        validFrom: DateTime(2026, 11, 20),
        validUntil: until,
        buyerName: 'Dilnoza',
        printedAt: DateTime(2026, 11, 20, 12, 15),
        isCopy: copy,
      );

  for (final PaperWidth paper in PaperWidth.values) {
    final int mm = paper == PaperWidth.mm58 ? 58 : 80;
    group('$paper', () {
      test(
        'a gift certificate: value, code as a QR and in writing, validity',
        () {
          final PrintLayout l = layoutGiftCertificate(
            certificate(until: DateTime(2027, 11, 19)),
            paper,
          );
          expectFits(l);
          expectGoldenText(renderPlainText(l), 'gift_certificate_$mm');
          final List<String> t = l.texts.map((String s) => s.trim()).toList();
          expect(
            t,
            containsAll(<String>[
              "SOVG'A SERTIFIKATI",
              "200 000 so'm",
              'KQMA-CDEF-ACDE',
              'Kimga: Dilnoza',
            ]),
          );
          expect(l.lines.whereType<PrintQr>().single.data, 'KQMA-CDEF-ACDE');
          expect(
            t.any(
              (String s) =>
                  s.startsWith('Amal qiladi:') &&
                  s.endsWith('19.11.2027 gacha'),
            ),
            isTrue,
          );
          expect(t, isNot(contains('NUSXA')));
          expect(l.lines.last, isA<PrintCut>());
          expect(l.title, 'Sertifikat KQMA-CDEF-ACDE');
        },
      );

      test('a certificate without an end date, reprinted', () {
        final PrintLayout l = layoutGiftCertificate(
          certificate(copy: true),
          paper,
        );
        final List<String> t = l.texts.map((String s) => s.trim()).toList();
        expect(t.any((String s) => s.endsWith('muddatsiz')), isTrue);
        expect(t.any((String s) => s.contains('NUSXA')), isTrue);
      });

      test('the pre-check carries the dynamic payment QR', () {
        final PreCheck base = preCheck();
        final PrintLayout l = layoutPreCheck(
          PreCheck(
            header: base.header,
            printedAt: base.printedAt,
            items: base.items,
            tableLabel: base.tableLabel,
            charges: base.charges,
            paymentQr: 'https://my.click.uz/services/pay?service_id=1&transaction_param=pos_qr_1',
            paymentQrLabel: "Click orqali to'lang: 2 950 000 so'm",
          ),
          paper,
        );
        expectFits(l);
        expect(l.lines.whereType<PrintQr>().single.data, contains('pos_qr_1'));
        final List<String> t = l.texts.map((String s) => s.trim()).toList();
        expect(t, contains("QR orqali to'lash"));
        expect(t, contains('*** Bu fiskal chek emas ***'));
        // Without a QR the pre-check is as before
        expect(layoutPreCheck(base, paper).lines.whereType<PrintQr>(), isEmpty);
      });

      test('X/Z: certificates and QR providers have their own sections', () {
        final ShiftReport base = shiftReport(ShiftReportKind.z);
        final ShiftReport r = ShiftReport(
          kind: base.kind,
          header: base.header,
          shiftNumber: base.shiftNumber,
          printedAt: base.printedAt,
          openedAt: base.openedAt,
          sales: base.sales,
          receiptsCount: base.receiptsCount,
          tenders: base.tenders,
          openingCash: base.openingCash,
          cashSales: base.cashSales,
          qrProviders: const <ReportAmount>[
            ReportAmount('Click', PosMoney.som(143000), count: 2),
            ReportAmount('Payme', PosMoney.som(52000), count: 1),
            ReportAmount("Statik QR (qo'lda)", PosMoney.som(30000), count: 1),
          ],
          vouchers: const <ReportAmount>[
            ReportAmount('Sotildi', PosMoney.som(400000), count: 2),
            ReportAmount('  naqd', PosMoney.som(200000)),
            ReportAmount('  karta', PosMoney.som(200000)),
            ReportAmount('Ishlatildi', PosMoney.som(120000), count: 3),
          ],
        );
        final PrintLayout l = layoutShiftReport(r, paper);
        expectFits(l);
        final String t = l.texts.join('\n');
        expect(t, contains("QR TO'LOVLAR"));
        expect(t, contains('Click (2):'));
        expect(t, contains('SERTIFIKATLAR'));
        expect(t, contains('Sotildi (2):'));
        expect(t, contains('Ishlatildi (3):'));
        // Nothing to show: no sections (the existing X/Z goldens stay as they are)
        final String plain = layoutShiftReport(base, paper).texts.join('\n');
        expect(plain, isNot(contains('SERTIFIKATLAR')));
        expect(plain, isNot(contains("QR TO'LOVLAR")));
      });
    });
  }
}
