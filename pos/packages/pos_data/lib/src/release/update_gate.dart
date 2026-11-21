import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';

/// Why "Yangilash" is not available now, or that it is.
class PosUpdateGate {
  const PosUpdateGate._(this.allowed, this.reason);

  /// The update may be started: nothing queued, no payment in progress.
  static const PosUpdateGate open = PosUpdateGate._(true, null);

  final bool allowed;

  /// Uzbek reason shown beside the disabled button; `null` when [allowed].
  final String? reason;
}

/// Reason texts (Uzbek).
abstract final class PosUpdateReasons {
  static const String queued =
      "Avval navbat serverga yetsin: internet qaytgach kuting.";
  static const String paying = "Avval joriy to'lovni yakunlang.";
}

/// May the "Yangilash" button open the download link now? Updating restarts the app, so never while money is
/// in flight: [pendingOperations] is the offline queue (every queued operation, also the sets kept aside from an
/// earlier connection, see [pendingOperationsOf]) and [paymentInProgress] an order mid-payment. Pure; the notice
/// itself is never hidden by this, only the button is disabled.
PosUpdateGate canUpdateNow({
  required int pendingOperations,
  required bool paymentInProgress,
}) {
  if (pendingOperations > 0) {
    return const PosUpdateGate._(false, PosUpdateReasons.queued);
  }
  if (paymentInProgress) {
    return const PosUpdateGate._(false, PosUpdateReasons.paying);
  }
  return PosUpdateGate.open;
}

/// Every operation still on the till that has not reached the server: the live queue plus the queues set aside
/// from an earlier connection. A terminal without an offline queue (the waiter) has none.
int pendingOperationsOf(PosOffline? offline) {
  if (offline == null) return 0;
  int total = offline.status.queueSize;
  for (final PosOrphanQueue o in offline.orphans) {
    total += o.operations.length;
  }
  return total;
}
