import 'package:pos_core/pos_core.dart';

/// Research report example (section 5): a 12-guest room on Thursday evening, 1 000 000 advance by Click, dishes
/// 3 800 000, room fee 300 000, 10% service 380 000, total 4 480 000 with 12% VAT inside.
const ReceiptHeader header = ReceiptHeader(
  storeName: "Choyxona Bog'bon",
  legalName: "\"Bog'bon Savdo\" MChJ",
  tin: '301234567',
  address: 'Toshkent, Yunusobod tumani, 4-mavze, 12-uy',
  phone: '+998 90 123 45 67',
);

final DateTime opened = DateTime(2026, 10, 22, 18, 5);
final DateTime closed = DateTime(2026, 10, 22, 23, 40);

ItemQuantity kg(String q, {String? label}) =>
    ItemQuantity(PosQuantity.parse(q), unit: PosUnit.kg, unitLabel: label);

ItemQuantity pcs(int n) => ItemQuantity(PosQuantity.whole(n));

ReceiptLine dish(
  String name,
  ItemQuantity q,
  int unitPrice, {
  String vat = '12',
  String mxik = '10202001001000000',
}) {
  final PosMoney price = PosMoney.som(unitPrice);
  return ReceiptLine(
    name: name,
    quantity: q,
    unitPrice: price,
    amount: price.timesQuantity(q.quantity),
    vatRate: vat,
    mxikCode: mxik,
  );
}

final List<ReceiptLine> roomLines = <ReceiptLine>[
  dish('Osh (devzira, sergo\'sht)', kg('5', label: 'kg (guruch)'), 248000),
  dish('Qozon kabob', kg('5'), 180000),
  dish('10 kishilik set (lagan)', pcs(1), 816000),
  dish('Choy (ko\'k)', pcs(12), 8000),
  dish('Non (patir)', pcs(12), 6000),
  dish('Achchiq-chuchuk salat', pcs(6), 21000),
  dish('Shashlik (qo\'y go\'shti)', pcs(20), 22000),
  dish('Mineral suv 1 l', pcs(10), 11000),
  dish('Xona puli', pcs(1), 300000, mxik: '10202002001000000'),
  dish('Xizmat haqi 10%', pcs(1), 380000, mxik: '10202002002000000'),
];

FiscalReceipt saleReceipt({FiscalInfo? fiscal, bool copy = false}) =>
    FiscalReceipt(
      type: FiscalReceiptType.sale,
      header: header,
      number: '000123',
      issuedAt: closed,
      cashier: 'Dilnoza',
      tableLabel: 'Xona 3',
      guests: 12,
      orderCode: '#E101',
      lines: roomLines,
      payments: const <ReceiptPayment>[
        ReceiptPayment(PosTender.prepayment, PosMoney.som(1000000)),
        ReceiptPayment(PosTender.cash, PosMoney.som(2000000)),
        ReceiptPayment(
          PosTender.card,
          PosMoney.som(1480000),
          cardType: 'Uzcard',
          reference: '612345678901',
        ),
      ],
      fiscal: fiscal,
      isCopy: copy,
    );

const FiscalInfo issued = FiscalInfo(
  terminalId: 'VK-000451',
  fiscalModuleId: 'UZ170703100153',
  fiscalNumber: '145',
  fiscalSign: '581923004417',
  qrUrl: 'https://ofd.soliq.uz/check?t=UZ170703100153&r=145&c=20261022234000&s=581923004417',
);

final FiscalReceipt advanceReceipt = FiscalReceipt(
  type: FiscalReceiptType.advance,
  header: header,
  number: '000098',
  issuedAt: DateTime(2026, 10, 19, 14, 20),
  cashier: 'Dilnoza',
  customerName: 'Jasur (+998 90 ***-**-17)',
  lines: <ReceiptLine>[
    dish('Avans (bron)', pcs(1), 1000000, mxik: '10202002004000001', vat: '0'),
  ],
  payments: const <ReceiptPayment>[
    ReceiptPayment(
      PosTender.qr,
      PosMoney.som(1000000),
      reference: 'CLICK-88412',
    ),
  ],
  note: 'Bron: 22.10.2026 19:00, Xona 3, 12 kishi',
);

final FiscalReceipt creditReceipt = FiscalReceipt(
  type: FiscalReceiptType.credit,
  header: header,
  number: '000140',
  issuedAt: DateTime(2026, 10, 29, 12, 0),
  cashier: 'Dilnoza',
  customerName: 'Anvar aka (+998 93 ***-**-41)',
  lines: <ReceiptLine>[
    dish("Qarz to'lovi", pcs(1), 200000, vat: '0', mxik: '10202001001000000'),
  ],
  payments: const <ReceiptPayment>[
    ReceiptPayment(PosTender.cash, PosMoney.som(200000)),
  ],
  remainingDebt: const PosMoney.som(300000),
);

PreCheck preCheck({bool copy = false, int? split}) => PreCheck(
  header: header,
  printedAt: DateTime(2026, 10, 22, 23, 30),
  openedAt: opened,
  tableLabel: 'Xona 3',
  waiter: 'Bekzod',
  guests: 12,
  orderCode: '#E101',
  items: <PreCheckItem>[
    PreCheckItem(
      name: "Osh (devzira, sergo'sht)",
      quantity: kg('5', label: 'kg (guruch)'),
      unitPrice: const PosMoney.som(248000),
      amount: const PosMoney.som(1240000),
      modifiers: const <String>['Qazi', 'Bedana tuxumi x2'],
    ),
    PreCheckItem(
      name: 'Qozon kabob',
      quantity: kg('2.5'),
      unitPrice: const PosMoney.som(180000),
      amount: const PosMoney.som(450000),
    ),
    PreCheckItem(
      name: "Choy (ko'k)",
      quantity: pcs(12),
      unitPrice: const PosMoney.som(8000),
      amount: const PosMoney.som(96000),
    ),
    PreCheckItem(
      name: 'Shashlik (qo\'y go\'shti)',
      quantity: pcs(20),
      unitPrice: const PosMoney.som(22000),
      amount: const PosMoney.som(440000),
    ),
  ],
  charges: const <BillCharge>[
    BillCharge(BillChargeKind.roomFee, PosMoney.som(300000)),
    BillCharge(
      BillChargeKind.serviceCharge,
      PosMoney.som(222600),
      percent: '10',
    ),
    BillCharge(BillChargeKind.minSpendTopUp, PosMoney.som(251400)),
    BillCharge(BillChargeKind.discount, PosMoney.som(-50000)),
  ],
  prepaid: const PosMoney.som(1000000),
  isCopy: copy,
  splitCount: split,
);

StationTicket kitchenTicket({bool held = false, bool isVoid = false}) =>
    StationTicket(
      stationName: 'Osh sexi',
      sentAt: DateTime(2026, 10, 22, 19, 42),
      tableLabel: 'Xona 3',
      orderCode: '#E101',
      waiter: 'Bekzod',
      round: 2,
      guests: 12,
      items: <StationTicketItem>[
        StationTicketItem(
          name: "Osh (devzira, sergo'sht)",
          quantity: kg('5', label: 'kg (guruch)'),
          modifiers: const <String>['Qazi', 'Bedana tuxumi x2'],
          note: "Kamroq yog', alohida likopchada piyoz",
        ),
        StationTicketItem(name: 'Qozon kabob', quantity: kg('2.5')),
        StationTicketItem(
          name: 'Achchiq-chuchuk salat',
          quantity: pcs(3),
          seat: 4,
        ),
      ],
      orderNote: isVoid ? null : 'Mehmonlar 20:30 da osh kutmoqda',
      held: held,
      dueAt: held ? DateTime(2026, 10, 22, 20, 30) : null,
      isVoid: isVoid,
      voidReason: isVoid ? 'Mijoz voz kechdi' : null,
      voidedBy: isVoid ? 'Dilnoza (menejer PIN)' : null,
    );

ShiftReport shiftReport(ShiftReportKind kind) {
  final bool z = kind == ShiftReportKind.z;
  return ShiftReport(
    kind: kind,
    header: header,
    shiftNumber: '45',
    businessDate: DateTime(2026, 10, 22),
    openedAt: DateTime(2026, 10, 22, 9, 0),
    openedBy: 'Dilnoza',
    closedAt: z ? DateTime(2026, 10, 23, 1, 10) : null,
    closedBy: z ? 'Dilnoza' : null,
    printedAt: z
        ? DateTime(2026, 10, 23, 1, 10)
        : DateTime(2026, 10, 22, 21, 0),
    receiptsCount: 38,
    sales: const PosMoney.som(24500000),
    refunds: const PosMoney.som(350000),
    discounts: const PosMoney.som(120000),
    serviceCharges: const PosMoney.som(1900000),
    roomFees: const PosMoney.som(2100000),
    tenders: const <ReportAmount>[
      ReportAmount('Naqd', PosMoney.som(12650000), count: 21),
      ReportAmount('Karta', PosMoney.som(7300000), count: 12),
      ReportAmount('QR orqali', PosMoney.som(1200000), count: 3),
      ReportAmount('Avans hisobidan', PosMoney.som(2500000), count: 3),
      ReportAmount('Nasiyaga', PosMoney.som(500000), count: 1),
    ],
    advancesReceived: const PosMoney.som(3000000),
    advancesApplied: const PosMoney.som(2500000),
    creditSales: const PosMoney.som(500000),
    creditRepayments: const PosMoney.som(200000),
    openingCash: const PosMoney.som(500000),
    cashSales: const PosMoney.som(12850000),
    cashRefunds: const PosMoney.som(350000),
    cashOut: const <ReportAmount>[
      ReportAmount('Bozorlik (go\'sht)', PosMoney.som(2400000)),
      ReportAmount('Ish haqi: Bekzod', PosMoney.som(250000)),
    ],
    countedCash: z ? const PosMoney.som(10345000) : null,
    denominations: z
        ? const <CashDenomination>[
            CashDenomination(200000, 30),
            CashDenomination(100000, 30),
            CashDenomination(50000, 25),
            CashDenomination(10000, 9),
            CashDenomination(5000, 1),
          ]
        : const <CashDenomination>[],
    voidedItems: 3,
    voidedAmount: const PosMoney.som(120000),
    fiscalPending: z ? 0 : 2,
    openOrders: z ? 0 : 4,
  );
}

final QueueSlip queueSlip = QueueSlip(
  header: header,
  number: '57',
  createdAt: DateTime(2026, 10, 22, 12, 15),
  orderCode: '#T057',
  orderLabel: 'Olib ketish',
  items: const <String>['1.5 kg Osh (lazer)', '2 x Non', '1 x Choy'],
);
