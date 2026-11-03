import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'label_test.dart' show expectInside, latte, printedAt;
import 'layout_test.dart' show expectFits;
import 'support/fixtures.dart';
import 'support/golden_text.dart';

/// Phase 6 wave 3 (fast food): a combo on the receipt (owner decision 30.10.2026: one priced line, its parts below
/// without prices), packaging lines with their own MXIK, the pickup time on the talon, packaging in the Z report, combo
/// parts on the kitchen ticket and the combo name on a drink's cup sticker.
const ReceiptLine comboLine = ReceiptLine(
  name: 'Lavash kombo',
  quantity: ItemQuantity(PosQuantity.one),
  unitPrice: PosMoney.som(52000),
  amount: PosMoney.som(52000),
  vatRate: '12',
  mxikCode: '10202001003000000',
  parts: <String>[
    "1 ta lavash — katta, mol go'shtli",
    'Ichimlik — 0,4 l Pepsi',
    'Sous — achchiq',
  ],
);

FiscalReceipt fastFoodReceipt() => FiscalReceipt(
  type: FiscalReceiptType.sale,
  header: header,
  number: '000321',
  issuedAt: DateTime(2026, 11, 20, 12, 15),
  cashier: 'Dilnoza',
  tableLabel: 'Olib ketish',
  orderCode: '#T057',
  lines: const <ReceiptLine>[
    comboLine,
    ReceiptLine(
      name: 'Fri (katta)',
      quantity: ItemQuantity(PosQuantity.one),
      unitPrice: PosMoney.som(18000),
      amount: PosMoney.som(18000),
      vatRate: '12',
      mxikCode: '10202001003000000',
    ),
    ReceiptLine(
      name: 'Paket',
      quantity: ItemQuantity(PosQuantity.one),
      unitPrice: PosMoney.som(1000),
      amount: PosMoney.som(1000),
      vatRate: '12',
      mxikCode: '10202002005000000',
      packageCode: '1500000',
    ),
  ],
  payments: const <ReceiptPayment>[
    ReceiptPayment(PosTender.cash, PosMoney.som(71000)),
  ],
  fiscal: issued,
);

void main() {
  for (final PaperWidth paper in PaperWidth.values) {
    final String mm = paper == PaperWidth.mm58 ? '58' : '80';

    group('$mm mm', () {
      void golden(String name, PrintLayout layout) {
        expectFits(layout);
        expectGoldenText(renderPlainText(layout), '${name}_$mm');
      }

      test('combo: one priced line, its parts below without prices', () {
        final PrintLayout l = layoutFiscalReceipt(fastFoodReceipt(), paper);
        golden('sale_receipt_combo', l);
        final List<String> texts = l.texts.toList();
        final int at = texts.indexWhere((String t) => t == 'Lavash kombo');
        expect(at, greaterThanOrEqualTo(0));
        final int price = texts.indexWhere(
          (String t) => t.contains('1 x 52 000'),
        );
        // The parts sit between the combo's name and its price line, with no price of their own
        final String parts = texts
            .sublist(at + 1, price)
            .map((String t) => t.trim())
            .join(' ');
        expect(
          parts,
          "1 ta lavash - katta, mol go'shtli Ichimlik - 0,4 l Pepsi Sous - achchiq",
        );
        // The packaging line is its own receipt line with its own MXIK
        expect(texts.join('\n'), contains('MXIK: 10202002005000000'));
        expect(fastFoodReceipt().total, const PosMoney.som(71000));
      });

      test('pre-check shows the combo parts', () {
        final PrintLayout l = layoutPreCheck(
          PreCheck(
            header: header,
            printedAt: DateTime(2026, 11, 20, 12, 14),
            orderCode: '#T057',
            tableLabel: 'Olib ketish',
            items: const <PreCheckItem>[
              PreCheckItem(
                name: 'Lavash kombo',
                quantity: ItemQuantity(PosQuantity.one),
                unitPrice: PosMoney.som(52000),
                amount: PosMoney.som(52000),
                parts: <String>[
                  "1 ta lavash — katta, mol go'shtli",
                  'Ichimlik — 0,4 l Pepsi',
                ],
              ),
            ],
            charges: const <BillCharge>[
              BillCharge(
                BillChargeKind.packaging,
                PosMoney.som(1000),
                label: 'Paket',
              ),
            ],
          ),
          paper,
        );
        golden('pre_check_combo', l);
        expect(l.texts, contains('  Ichimlik - 0,4 l Pepsi'));
      });

      test('talon of a scheduled pickup shows the time', () {
        final PrintLayout l = layoutQueueSlip(
          QueueSlip(
            header: header,
            number: '57',
            createdAt: DateTime(2026, 11, 20, 12, 15),
            orderCode: 'T057',
            orderLabel: 'Olib ketish',
            items: const <String>['1 x Lavash kombo', '1 x Fri'],
            pickupAt: DateTime(2026, 11, 20, 13, 30),
          ),
          paper,
        );
        golden('queue_slip_pickup', l);
        expect(
          l.texts.map((String t) => t.trim()),
          contains('Olib ketish: 13:30'),
        );
      });

      test('Z report: packaging inside the sales', () {
        final ShiftReport base = shiftReport(ShiftReportKind.z);
        final ShiftReport z = ShiftReport(
          kind: base.kind,
          header: base.header,
          shiftNumber: base.shiftNumber,
          printedAt: base.printedAt,
          openedAt: base.openedAt,
          closedAt: base.closedAt,
          receiptsCount: 60,
          sales: const PosMoney.som(3120000),
          packaging: const PosMoney.som(60000),
          tenders: const <ReportAmount>[
            ReportAmount('Naqd', PosMoney.som(3120000), count: 60),
          ],
          cashSales: const PosMoney.som(3120000),
          countedCash: const PosMoney.som(3120000),
        );
        final PrintLayout l = layoutShiftReport(z, paper);
        golden('z_report_packaging', l);
        expect(
          l.texts.where((String t) => t.startsWith('Shu jumladan qadoq:')),
          hasLength(1),
        );
      });

      test('kitchen ticket: the combo parts under the combo', () {
        final PrintLayout l = layoutStationTicket(
          StationTicket(
            stationName: 'Oshxona',
            sentAt: DateTime(2026, 11, 20, 12, 15),
            orderLabel: 'Olib ketish',
            orderCode: 'T057',
            items: const <StationTicketItem>[
              StationTicketItem(
                name: 'Lavash kombo',
                quantity: ItemQuantity(PosQuantity.one),
                parts: <String>[
                  "Asosiy — Lavash (katta), mol go'shtli",
                  'Ichimlik — Pepsi (0,4 l)',
                ],
              ),
            ],
          ),
          paper,
        );
        golden('kitchen_ticket_combo', l);
        expect(
          l.texts.map((String t) => t.trim()),
          contains('- Ichimlik - Pepsi (0,4 l)'),
        );
      });
    });
  }

  for (final LabelSize size in LabelSize.values) {
    test('${size.wire}: a drink inside a combo names its combo', () {
      final CupSticker plain = latte(
        name: 'Pepsi',
        modifiers: const <String>[],
      );
      final LabelLayout l = layoutCupSticker(
        CupSticker(
          number: plain.number,
          itemName: plain.itemName,
          variantName: plain.variantName,
          printedAt: printedAt,
          context: 'Lavash kombo',
          index: 1,
          total: 2,
        ),
        size,
      );
      expectInside(l);
      expectGoldenText(
        renderLabelPlainText(l),
        'cup_sticker_combo_${size.wire}',
      );
      expect(l.texts.map((LabelText t) => t.text), contains('[Lavash kombo]'));
    });
  }
}
