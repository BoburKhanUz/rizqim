import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'support/fixtures.dart';
import 'support/golden_text.dart';

/// Every text line of a layout fits the paper (in its own font width).
void expectFits(PrintLayout layout) {
  for (final PrintLine line in layout.lines) {
    if (line is PrintText) {
      expect(
        textWidth(line.text),
        lessThanOrEqualTo(layout.paper.chars ~/ line.size.width),
        reason: '"${line.text}" is wider than ${layout.paper}',
      );
    }
  }
}

String texts(PrintLayout layout) => layout.texts.join('\n');

void main() {
  for (final PaperWidth paper in PaperWidth.values) {
    final String mm = paper == PaperWidth.mm58 ? '58' : '80';

    group('$mm mm', () {
      void golden(String name, PrintLayout layout) {
        expectFits(layout);
        expectGoldenText(renderPlainText(layout), '${name}_$mm');
      }

      test('sale receipt with fiscal sign and QR', () {
        final PrintLayout l = layoutFiscalReceipt(
          saleReceipt(fiscal: issued),
          paper,
        );
        golden('sale_receipt', l);
        expect(l.lines.whereType<PrintQr>().single.data, issued.qrUrl);
        expect(texts(l), contains('Fiskal belgi: 581923004417'));
        expect(texts(l), contains('Shu jumladan QQS 12%:'));
        expect(texts(l), contains("To'lov shakli: Aralash"));
        expect(l.lines.last, isA<PrintCut>());
      });

      test('sale receipt while the fiscal receipt is pending', () {
        final PrintLayout l = layoutFiscalReceipt(
          saleReceipt(copy: true),
          paper,
        );
        golden('sale_receipt_pending', l);
        expect(l.lines.whereType<PrintQr>(), isEmpty);
        expect(texts(l), contains("Fiskal ma'lumot kutilmoqda"));
        expect(texts(l), contains('NUSXA'));
      });

      test(
        "advance (bo'nak) and credit receipts have no QR or fiscal sign",
        () {
          final PrintLayout a = layoutFiscalReceipt(advanceReceipt, paper);
          final PrintLayout c = layoutFiscalReceipt(creditReceipt, paper);
          golden('advance_receipt', a);
          golden('credit_receipt', c);
          for (final PrintLayout l in <PrintLayout>[a, c]) {
            expect(l.lines.whereType<PrintQr>(), isEmpty);
            expect(texts(l), isNot(contains('Fiskal belgi:')));
          }
          expect(texts(a), contains("BO'NAK CHEKI"));
          expect(texts(c), contains('Qolgan qarz:'));
        },
      );

      test('pre-check: not fiscal, separate charge lines, per person', () {
        final PrintLayout l = layoutPreCheck(preCheck(), paper);
        golden('pre_check', l);
        final String t = texts(l);
        expect(t, contains('*** Bu fiskal chek emas ***'));
        expect(t, contains('Xona puli:'));
        expect(t, contains('Xizmat haqi 10%:'));
        expect(t, contains("Minimal summaga to'ldirish:"));
        expect(t, contains('Kishi boshiga (12):'));
        expect(t, isNot(contains('NUSXA')));
        expect(preCheck().total, const PosMoney.som(2950000));
        expect(preCheck().toPay, const PosMoney.som(1950000));
        expect(preCheck().perPerson, const PosMoney.som(162500));
      });

      test('pre-check reprint is marked NUSXA', () {
        final PrintLayout l = layoutPreCheck(
          preCheck(copy: true, split: 5),
          paper,
        );
        golden('pre_check_copy', l);
        final PrintText mark = l.lines.whereType<PrintText>().firstWhere(
          (PrintText t) => t.text.contains('NUSXA'),
        );
        expect(mark.inverted, isTrue);
        expect(texts(l), contains('Kishi boshiga (5):'));
      });

      test('kitchen ticket per station, held with due time, void ticket', () {
        golden('kitchen_ticket', layoutStationTicket(kitchenTicket(), paper));
        final PrintLayout held = layoutStationTicket(
          kitchenTicket(held: true),
          paper,
        );
        golden('kitchen_ticket_held', held);
        expect(texts(held), contains("Tayyor bo'lsin: 20:30"));
        final PrintLayout v = layoutStationTicket(
          kitchenTicket(isVoid: true),
          paper,
        );
        golden('kitchen_ticket_void', v);
        expect(texts(v), contains("O'CHIRILDI"));
        expect(texts(v), contains('Sabab: Mijoz voz kechdi'));
        expect(texts(v), isNot(contains("so'm")));
      });

      test('X and Z reports', () {
        final PrintLayout x = layoutShiftReport(
          shiftReport(ShiftReportKind.x),
          paper,
        );
        final PrintLayout z = layoutShiftReport(
          shiftReport(ShiftReportKind.z),
          paper,
        );
        golden('x_report', x);
        golden('z_report', z);
        expect(texts(x), contains('Fiskal kutmoqda:'));
        expect(texts(z), contains('Kamomad:'));
        expect(texts(z), contains('-5 000'));
      });

      test('queue number slip', () {
        final PrintLayout l = layoutQueueSlip(queueSlip, paper);
        golden('queue_slip', l);
        expect(
          l.lines
              .whereType<PrintText>()
              .firstWhere((PrintText t) => t.text == '57')
              .size,
          PrintTextSize.huge,
        );
      });
    });
  }

  test('P3-37: the order discount on the sale receipt and the pre-check', () {
    FiscalReceipt sale({PosMoney lineDiscount = PosMoney.zero}) =>
        FiscalReceipt(
          type: FiscalReceiptType.sale,
          header: header,
          number: '000124',
          issuedAt: closed,
          lines: <ReceiptLine>[
            ReceiptLine(
              name: 'Osh',
              quantity: pcs(1),
              unitPrice: const PosMoney.som(171000),
              amount: const PosMoney.som(171000),
              discount: lineDiscount,
            ),
          ],
          payments: const <ReceiptPayment>[
            ReceiptPayment(PosTender.cash, PosMoney.som(171000)),
          ],
          discount: const PosMoney.som(19000),
          discountPercent: '10.00',
        );
    for (final PaperWidth paper in PaperWidth.values) {
      // The server's lines are after the discount: it is told once at the end, the total stays the lines' sum
      final PrintLayout told = layoutFiscalReceipt(sale(), paper);
      expectFits(told);
      expect(texts(told), contains('Berilgan chegirma 10%:'));
      expect(texts(told), contains('19 000'));
      expect(sale().total, const PosMoney.som(171000));
      // Lines that show their own discount: no second line
      final PrintLayout perLine = layoutFiscalReceipt(
        sale(lineDiscount: const PosMoney.som(19000)),
        paper,
      );
      expect(texts(perLine), isNot(contains('Berilgan chegirma')));
      expect(texts(perLine), contains('Chegirma'));

      final PreCheck check = PreCheck(
        header: header,
        printedAt: closed,
        items: <PreCheckItem>[
          PreCheckItem(
            name: 'Osh',
            quantity: pcs(1),
            unitPrice: const PosMoney.som(190000),
            amount: const PosMoney.som(190000),
          ),
        ],
        charges: const <BillCharge>[
          BillCharge(
            BillChargeKind.discount,
            PosMoney.som(-19000),
            percent: '10.00',
          ),
        ],
      );
      final PrintLayout pre = layoutPreCheck(check, paper);
      expectFits(pre);
      expect(texts(pre), contains('Chegirma 10%:'));
      expect(texts(pre), contains('-19 000'));
      expect(check.total, const PosMoney.som(171000));
    }
  });

  test('receipt totals: VAT by rate and money received', () {
    final FiscalReceipt r = saleReceipt(fiscal: issued);
    expect(r.total, const PosMoney.som(4480000));
    expect(r.vatTotals.single.rate, '12');
    expect(r.received, const PosMoney.som(3480000));
  });

  test('writer pairs a long name with its amount on the last line', () {
    final PrintWriter w = PrintWriter(PaperWidth.mm58)
      ..pair("Osh (devzira, sergo'sht, birga-bir) maxsus", '1 240 000');
    final List<String> lines = w.lines
        .whereType<PrintText>()
        .map((PrintText t) => t.text)
        .toList();
    expect(lines.last, endsWith('1 240 000'));
    expect(lines.every((String l) => textWidth(l) <= 32), isTrue);
  });
}
