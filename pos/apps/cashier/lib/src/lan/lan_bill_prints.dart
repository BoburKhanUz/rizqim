import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';

import '../printing/bill_print_service.dart';

/// P6 B bosqichi: internetsiz paytda ofitsiant LAN orqali "Schyot so'rash"ni bossa, markaz kassa pre-check'ni o'zi
/// chiqaradi. Qachon va nima chiqishi — `pos_lan` [LanBillPrints] da (review P6 C6/C9: e2e mashq ham shu mantiqni
/// ishlatadi); bu yerda faqat kassaning printeri ([BillPrintService], so'rov id'si — amalning `op_id` si, serverda
/// olinmaydi).
LanBillPrints tillLanBillPrints({
  required PosOffline offline,
  required BillPrintService prints,
  required DateTime Function() now,
  required bool Function() autoPrint,
  int? Function()? printerId,
  Duration takeOverAfter = const Duration(seconds: 8),
}) => LanBillPrints(
  offline: offline,
  print: (BillPrintRequest request, PosBilling billing, PosOrder order) async =>
      await prints.handle(
        request,
        localBilling: billing,
        localOrder: order,
        claim: false,
      ) ==
      BillPrintOutcome.queued,
  canPrint: prints.canPrint,
  autoPrint: autoPrint,
  printerId: printerId,
  now: now,
  takeOverAfter: takeOverAfter,
);
