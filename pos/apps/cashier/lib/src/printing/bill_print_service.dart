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
    this.signedIn,
    this.printedOnLan,
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

  /// Review P6 uskunalar/LAN (C2): kassada xodim kirganmi (bootstrap yuklangan). Yo'q bo'lsa (PIN ekrani) schyotni
  /// xodim sessiyasi bilan o'qib bo'lmaydi: so'rov serverdagi ro'yxatdan ([catchUp]) chiqariladi. `null` — doim kirgan.
  final bool Function()? signedIn;

  /// Review P6 uskunalar/LAN (C6): shu so'rov (`request_id` — LAN amalining `op_id` si) markaz kassada LAN orqali
  /// allaqachon chiqarilganmi ([LanBillPrints.printed]): server keyin uni e'lon qilsa, qayta chiqmaydi.
  final bool Function(String requestId)? printedOnLan;

  final BillPrintRequests _seen = BillPrintRequests();
  Future<void>? _catchingUp;
  bool _catchUpAgain = false;

  /// Review P6 uskunalar/LAN (C2): server saqlagan, shu kassa hali chiqarmagan so'rovlar
  /// (`GET /pos/bill-print-requests`, terminal tokeni bilan) — ishga tushganda, qayta ulanganda, qulfdan ochilganda
  /// va qulflangan kassaga hodisa kelganda. Har biri [handle] dan o'tadi (bir marta, olib — `claim`). Hech qachon xato
  /// bermaydi; bir vaqtda bittasi ishlaydi (yana so'ralsa, tugagach yana bir marta o'qiladi).
  Future<void> catchUp() {
    final Future<void>? running = _catchingUp;
    if (running != null) {
      _catchUpAgain = true;
      return running;
    }
    final Future<void> run = _catchUp().whenComplete(() => _catchingUp = null);
    _catchingUp = run;
    return run;
  }

  Future<void> _catchUp() async {
    do {
      _catchUpAgain = false;
      final List<PendingBillPrint> pending;
      try {
        pending = await data.billing.pendingPrintRequests();
      } on Object {
        // Offline or an older server: the next reconnect, unlock or poll reads it again
        return;
      }
      for (final PendingBillPrint p in pending) {
        try {
          await handle(
            p.request,
            localBilling: p.billing,
            localOrder: p.order,
            claim: true,
          );
        } on Object {
          // One request never stops the others
        }
      }
    } while (_catchUpAgain);
  }

  /// [localBilling] va [localOrder] berilsa (P6 B bosqichi: markaz LAN orqali olgan so'rov, internetsiz; C2: serverdagi
  /// ro'yxatdan) schyot serverdan o'qilmaydi — berilgani chiqadi.
  ///
  /// [claim] (serverdan kelgan so'rov; standart — [localBilling] berilmagan bo'lsa): chiqarishdan oldin so'rov serverda
  /// olinadi (`POST /pos/bill-print-requests/{id}/printed`): boshqa kassa olgan bo'lsa chiqmaydi; server javob
  /// bermasa — baribir chiqadi (schyot jim yo'qolmaydi). Xodim kirmagan kassa (PIN ekrani) hodisani [catchUp] bilan
  /// ishlaydi.
  Future<BillPrintOutcome> handle(
    BillPrintRequest request, {
    PosBilling? localBilling,
    PosOrder? localOrder,
    bool? claim,
  }) async {
    final bool server = claim ?? localBilling == null;
    if (!request.isFor(deviceUuid())) return BillPrintOutcome.notForThisTill;
    // The screen is locked: no staff session reads the bill; the server's list carries it (device token)
    if (localBilling == null && !(signedIn?.call() ?? true)) {
      await catchUp();
      return BillPrintOutcome.queued;
    }
    if (!_seen.accept(request)) return BillPrintOutcome.duplicate;
    // C6: the hub printed this relayed request over the LAN already (the server announces it with the op's id)
    if (server && (printedOnLan?.call(request.requestId) ?? false)) {
      await _claim(request);
      return BillPrintOutcome.duplicate;
    }

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

    // One paper per request across the branch's tills (and a catch-up racing the broadcast)
    if (server && !await _claim(request)) return BillPrintOutcome.duplicate;

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

  /// `false` — boshqa kassa oldi; server javob bermasa yoki so'rovni bilmasa — `true` (chiqadi).
  Future<bool> _claim(BillPrintRequest request) async {
    try {
      return await data.billing.claimPrintRequest(request.requestId) ?? true;
    } on Object {
      return true;
    }
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
