import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

import '../features/billing/billing_page.dart' show depositShare;
import '../features/billing/receipts.dart' show preCheckOf;
import '../features/order/kitchen_tickets.dart' show KitchenPrintIssues;
import 'printing_service.dart';

/// Bir so'rov qanday tugadi ([BillPrintService.handle]).
enum BillPrintOutcome {
  /// Chop etish navbatiga tushdi (qog'ozga chiqmasa, chek printeri bannerida "Qayta urinish").
  queued,

  /// So'rov boshqa kassaga (`target_device`).
  notForThisTill,

  /// Shu so'rov oldin ishlangan (bir hodisa ikki marta keldi).
  duplicate,

  /// Schyot bekor qilingan yoki topilmadi (eskirgan so'rov).
  stale,

  /// Chek printeri yo'q yoki sozlanmagan.
  noPrinter,

  /// Schyotni o'qib bo'lmadi (aloqa yo'q): so'rov qayta kelsa yana urinadi.
  unavailable,
}

/// Ofitsiant so'ragan schyotni (pre-check, "Bu fiskal chek emas") avtomatik chiqaradi (P6, A bosqichi).
///
/// Transportga bog'liq emas: so'rov ([BillPrintRequest]) serverdan `bill.print_requested` bilan ham, keyingi bosqichda
/// LAN ko'prigidan ham keladi; ikkalasi [handle] ga tushadi. Bir so'rov ([BillPrintRequest.billUuid] +
/// [BillPrintRequest.requestId]) bir marta chiqadi. Faqat so'rovda nomlangan kassa chiqaradi
/// ([BillPrintRequest.isFor]; nomlanmagan bo'lsa — hamma kassa).
///
/// Printer: so'rovdagi `printer_id` (bu kassada ishlatsa bo'ladigan bo'lsa), bo'lmasa kassaning chek printeri.
/// Schyotni kassa o'zi serverdan o'qiydi (`GET /pos/orders/{uuid}/bills`) va "chiqarilgan" hisobini o'zgartirmaydi
/// (`POST /pos/bills/{uuid}/print` ni chaqirmaydi): bu pre-check, schyotni mehmonga topshirish emas. Chop etilmasa
/// ([KitchenPrintIssues.receiptsOf] bannerida) kassir "Qayta urinish"ni bosadi; printer yo'q yoki schyotni o'qib
/// bo'lmasa [onProblem] xabar beradi. Hech qachon to'lov, yopish yoki smenani to'smaydi.
class BillPrintService {
  BillPrintService({
    required this.data,
    required this.printing,
    required this.deviceUuid,
    required this.now,
    required this.header,
    this.adjust,
    this.onProblem,
  });

  final PosData data;
  final CashierPrinting printing;

  /// Shu kassaning qurilma uuid'si (bootstrap `device.uuid`).
  final String? Function() deviceUuid;
  final DateTime Function() now;
  final ReceiptHeader Function() header;

  /// Qurilmadagi printer sozlamalari (qog'oz eni, rasm rejimi) ustidan qo'llanadi.
  final PrinterConfig Function(PrinterConfig config)? adjust;

  /// Foydalanuvchiga ko'rsatiladigan xabar (o'zbekcha); ish ekrani ([HomeShell]) o'rnatadi.
  void Function(String message)? onProblem;

  final BillPrintRequests _seen = BillPrintRequests();

  /// [localBilling] va [localOrder] berilsa (P6 B bosqichi: markaz LAN orqali olgan so'rov, internetsiz) schyot serverdan
  /// o'qilmaydi — kassaning o'z ko'rinishi chiqadi.
  Future<BillPrintOutcome> handle(
    BillPrintRequest request, {
    PosBilling? localBilling,
    PosOrder? localOrder,
  }) async {
    if (!request.isFor(deviceUuid())) return BillPrintOutcome.notForThisTill;
    if (!_seen.accept(request)) return BillPrintOutcome.duplicate;

    final PrinterConfig? printer = _printerFor(request);
    if (printer == null) {
      _seen.forget(request);
      onProblem?.call(
        "Schyot chiqmadi: chek printeri sozlanmagan. Boshqaruv panelida Printerlar bo'limidan qo'shing.",
      );
      return BillPrintOutcome.noPrinter;
    }

    final PosBilling billing;
    final PosOrder order;
    try {
      billing = localBilling ?? await data.billing.bills(request.orderUuid);
      order = localOrder ?? await data.orders.get(request.orderUuid);
    } on PosNotFound {
      return BillPrintOutcome.stale;
    } on PosException {
      _seen.forget(request);
      onProblem?.call(
        "Schyot chiqmadi: aloqa yo'q. Buyurtmadan schyotni qo'lda chiqaring.",
      );
      return BillPrintOutcome.unavailable;
    }

    PosBill? bill;
    for (final PosBill b in billing.bills) {
      if (b.uuid == request.billUuid) bill = b;
    }
    if (bill == null || bill.status == 'void') return BillPrintOutcome.stale;

    final PreCheck check = preCheckOf(
      bill: bill,
      order: order,
      header: header(),
      now: now(),
      billsCount: billing.bills.where((PosBill b) => b.status != 'void').length,
      prepaid: depositShare(
        billing.bills,
        bill,
        billing.order.depositAvailable,
      ),
      copy: request.reprint,
    );
    try {
      final PrintJob job = await printing.printers.print(
        printer,
        layoutPreCheck(check, printer.paper),
      );
      KitchenPrintIssues.receiptsOf(printing)
          .watch(printing.printers.queue, job);
    } on Object catch (e) {
      _seen.forget(request);
      KitchenPrintIssues.receiptsOf(printing)
          .reportRender('Schyot ${bill.number}', '$e');
      onProblem?.call('Schyot chiqmadi: $e');
      return BillPrintOutcome.noPrinter;
    }
    return BillPrintOutcome.queued;
  }

  /// Shu kassada [printerId] (yoki chek printeri) bilan schyot chiqarsa bo'ladimi (P6 B: markaz LAN so'rovini o'zi
  /// chiqarishdan oldin so'raydi).
  bool canPrint(int? printerId) =>
      ((printerId == null ? null : printing.configFor(printerId)) ??
          printing.receiptPrinter) !=
      null;

  PrinterConfig? _printerFor(BillPrintRequest request) {
    final int? id = request.printerId;
    final PrinterConfig? config =
        (id == null ? null : printing.configFor(id)) ?? printing.receiptPrinter;
    if (config == null) return null;
    return adjust == null ? config : adjust!(config);
  }
}
