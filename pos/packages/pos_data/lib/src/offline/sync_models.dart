import '../json.dart';
import '../http/pos_exceptions.dart';

/// Internetsiz rejimda bajarib bo'lmaydigan amal (docs/POS_SPEC.md 5.1): sababi o'zbekcha ([message]). Server
/// so'ralmagan, shuning uchun bu tarmoq xatosi emas (`statusCode` 503: "hozir mumkin emas").
class PosOfflineUnavailable extends PosException {
  const PosOfflineUnavailable(
    String message, {
    super.reason = 'offline_unavailable',
  }) : super(message: message, statusCode: 503);
}

/// "Sinxronlash xatolari": serverda rad etilgan yoki farq bilan qo'llangan oflayn amal (`GET /pos/sync/errors`,
/// `GET /pos/sync/status`).
class PosSyncError {
  const PosSyncError({
    required this.opId,
    required this.seq,
    required this.type,
    required this.typeLabel,
    required this.status,
    this.reason,
    this.message,
    this.staffName,
    this.deviceName,
    this.occurredAt,
    this.receivedAt,
    this.orderUuid,
    this.localNumber,
    this.tableRef,
    this.resolvedAt,
    this.resolution,
    this.resolutionNote,
    this.amount,
  });

  factory PosSyncError.fromJson(PosJson j) {
    final PosJson? order = j.objOrNull('order');
    final PosJson? payload = j.objOrNull('payload');
    String? amount;
    final Object? tenders = payload?['tenders'];
    if (tenders is List && tenders.isNotEmpty) {
      var sum = 0;
      for (final Object? t in tenders) {
        if (t is Map) sum += PosJson.of(t).money('amount').tiyin;
      }
      amount = (sum ~/ 100).toString();
    }
    return PosSyncError(
      opId: j.str('op_id'),
      seq: j.intOrNull('seq') ?? 0,
      type: j.str('type'),
      typeLabel: j.strOrNull('type_label') ?? j.str('type'),
      status: j.str('status'),
      reason: j.strOrNull('reason'),
      message: j.strOrNull('message'),
      staffName: j.objOrNull('staff')?.strOrNull('name'),
      deviceName: j.objOrNull('device')?.strOrNull('name'),
      occurredAt: j.dateOrNull('occurred_at'),
      receivedAt: j.dateOrNull('received_at'),
      orderUuid: order?.strOrNull('uuid'),
      localNumber:
          order?.strOrNull('local_number') ??
          payload?.strOrNull('local_number'),
      tableRef: order?.strOrNull('table_ref'),
      resolvedAt: j.dateOrNull('resolved_at'),
      resolution: j.strOrNull('resolution'),
      resolutionNote: j.strOrNull('resolution_note'),
      amount: amount,
    );
  }

  final String opId;
  final int seq;
  final String type;
  final String typeLabel;

  /// `rejected` (qo'llanmadi) yoki `conflict` (farq bilan qo'llandi).
  final String status;
  final String? reason;
  final String? message;
  final String? staffName;
  final String? deviceName;
  final DateTime? occurredAt;
  final DateTime? receivedAt;
  final String? orderUuid;
  final String? localNumber;
  final String? tableRef;
  final DateTime? resolvedAt;

  /// `resolved` yoki `dismissed`.
  final String? resolution;
  final String? resolutionNote;

  /// To'lov amalida olingan summa (so'm), boshqasida `null`.
  final String? amount;

  bool get isRejected => status == 'rejected';
  bool get isResolved => resolvedAt != null;
}

/// `GET /pos/sync/errors` sahifasi.
class PosSyncErrorsPage {
  const PosSyncErrorsPage({required this.errors, this.total = 0});

  factory PosSyncErrorsPage.fromJson(PosJson j) => PosSyncErrorsPage(
    errors: j.objects('errors', PosSyncError.fromJson),
    total: j.objOrNull('meta')?.intOrNull('total') ?? 0,
  );

  final List<PosSyncError> errors;
  final int total;
}
