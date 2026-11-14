import 'package:flutter/widgets.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

import '../../shell/pos_scope.dart';
import '../billing/receipts.dart';
import 'banquet_format.dart';

/// P6-53/P6-54: tadbir hujjatlari chek printerida (`pos_core` sxemalari): bo'nak cheki (zaklad, oraliq to'lov),
/// qaytarish va "Bekor qilish haqi" cheklari, shartnoma, ishlab chiqarish varag'i, bozorlik ro'yxati. A4 — panelda.

/// Pul yozuvining chek turi: `payment` — bo'nak, `refund` — qaytarish, `retain` — bekor qilish haqi (sotuv).
FiscalReceiptType _typeOf(String entryType) => switch (entryType) {
  'refund' => FiscalReceiptType.refund,
  'retain' => FiscalReceiptType.sale,
  _ => FiscalReceiptType.advance,
};

String _nameOf(PosBanquetPaymentEntry entry, PosBanquetPaymentKind kind) =>
    switch (entry.type) {
      'refund' => 'Tadbir zakladi qaytarildi',
      'retain' => 'Bekor qilish haqi',
      _ =>
        kind == PosBanquetPaymentKind.deposit
            ? 'Tadbir zakladi (avans)'
            : "Tadbir to'lovi (avans)",
    };

/// Tadbirning pul yozuvi chekini chop etadi ([detail] — serverdagi chek; bo'lmasa o'qiladi). [openDrawer] — naqd.
Future<bool> printBanquetEntry(
  BuildContext context, {
  required PosBanquetEvent event,
  required PosBanquetPaymentEntry entry,
  PosBanquetPaymentKind kind = PosBanquetPaymentKind.unknown,
  PosFiscalReceiptDetail? detail,
  bool copy = false,
  bool openDrawer = false,
}) async {
  final PosScope scope = PosScope.of(context);
  final PosFiscalReceiptDetail? receipt =
      detail ??
      await receiptOf(
        scope.data,
        PosFiscalState(
          status: entry.fiscalStatus,
          receiptUuid: entry.fiscalReceiptUuid,
        ),
      );
  if (!context.mounted) return false;
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final FiscalReceipt doc = banquetReceiptOf(
    type: _typeOf(entry.type),
    header: printer.header,
    now: scope.services.now(),
    name: _nameOf(entry, kind),
    amount: entry.amount,
    tender: entry.tender,
    detail: receipt,
    cashier: printer.cashier,
    customerName: event.customerName,
    cardType: entry.cardType,
    reference: entry.reference,
    copy: copy,
    // The receipt printer's text mode: a plain hyphen (paperPart)
    note: paperPart(
      'Tadbir: ${event.displayName}, ${banquetDate(event.eventDate)} '
      '${event.startTime}-${event.endTime}',
    ),
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutFiscalReceipt(doc, paper),
    openDrawer: openDrawer,
  );
}

/// Shartnoma matni chek printerida (`banquets.manage`).
Future<bool> printBanquetContract(BuildContext context, String uuid) async {
  final PosScope scope = PosScope.of(context);
  final PosBanquetContract c = await scope.data.banquetOps.contract(uuid);
  if (!context.mounted) return false;
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final BanquetContractDoc doc = BanquetContractDoc(
    header: printer.header,
    contractNo: c.contractNo,
    lines: c.lines,
    printedAt: scope.services.now().toLocal(),
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutBanquetContract(doc, paper),
  );
}

BanquetDishLine _dish(PosBanquetDish d) =>
    BanquetDishLine(name: d.name, quantity: d.quantity, unit: d.unit, kg: d.kg);

BanquetIngredientLine _ingredient(PosOshIngredient i) =>
    BanquetIngredientLine(name: i.name, quantity: i.quantity, unit: i.unit);

/// Ishlab chiqarish varag'i (oshxona uchun; mijoz ma'lumoti yo'q).
Future<bool> printBanquetProductionSheet(
  BuildContext context,
  String uuid,
) async {
  final PosScope scope = PosScope.of(context);
  final PosProductionSheet s = await scope.data.banquetOps.productionSheet(
    uuid,
  );
  if (!context.mounted) return false;
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final BanquetProductionSheetDoc doc = BanquetProductionSheetDoc(
    header: printer.header,
    title: s.title,
    eventDate: banquetDate(s.eventDate),
    localTime: s.localTime,
    halls: s.halls,
    guestsBase: s.guestsBase,
    guestsPlanned: s.guestsPlanned,
    reservePercent: s.reservePercent,
    printedAt: scope.services.now().toLocal(),
    stations: <(String, List<BanquetDishLine>)>[
      for (final (String name, List<PosBanquetDish> dishes) in s.stations)
        (name, dishes.map(_dish).toList()),
    ],
    oshPersons: s.osh?.persons,
    oshKg: s.osh?.oshKg,
    kazans: s.osh?.kazans ?? const <(int, int)>[],
    ingredients:
        s.osh?.ingredients.map(_ingredient).toList() ??
        const <BanquetIngredientLine>[],
    warnings: <String>[for (final PosBanquetWarning w in s.warnings) w.message],
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutBanquetProductionSheet(doc, paper),
  );
}

/// Bozorlik ro'yxati [from]–[to] (`YYYY-MM-DD`).
Future<bool> printBanquetShoppingList(
  BuildContext context, {
  required String from,
  required String to,
}) async {
  final PosScope scope = PosScope.of(context);
  final PosShoppingList l = await scope.data.banquetOps.shoppingList(
    from: from,
    to: to,
  );
  if (!context.mounted) return false;
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final BanquetShoppingListDoc doc = BanquetShoppingListDoc(
    header: printer.header,
    from: banquetDate(l.from),
    to: banquetDate(l.to),
    printedAt: scope.services.now().toLocal(),
    events: <(String, String, int)>[
      for (final (String _, String date, String title, int guests) in l.events)
        (banquetDate(date), title, guests),
    ],
    ingredients: l.ingredients.map(_ingredient).toList(),
    dishes: l.dishes.map(_dish).toList(),
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutBanquetShoppingList(doc, paper),
  );
}
