/// Which `private-pos-branch.{id}` events can change what a screen shows (wave M2 fix round 2). A screen reads only on
/// those; EVERY event still feeds its revision bookkeeping ([PacedRefresh.event] with `relevant: false`).
///
/// Checked against the endpoint each screen reads and against where the backend dispatches each event (paths under
/// `backend/app`; `Events/Broadcast` unless named otherwise):
///
/// | event | dispatched from | KDS (`/staff/kitchen/tickets`) | floors (`/pos/floor`) | pickup board (`/pos/queue`) | customer display (`/pos/display/state`, `/pos/queue`) |
/// |---|---|---|---|---|---|
/// | `order.created` | `Domain/Pos/Orders/PosOrderService` | yes | yes | yes | no |
/// | `order.status_changed` | `Services/Order/OrderStatusService`, `PosOrderService` (also when payment closes an order: `Billing/OrderCloser`) | yes | yes | yes | no |
/// | `order.assigned` | `OrderStatusService` | yes | yes | yes | no |
/// | `order.items_changed` | `PosOrderService`, `Takeaway/ScheduledPickup`, QR provider | yes | yes | no | no |
/// | `order.bill_changed` `created`/`rebilled`/`printed`/`voided`/other | `Billing/BillService` | no (1) | yes | no | no |
/// | `order.bill_changed` `payment` | `Billing/PaymentService` | no (1) | no (2) | no | no |
/// | `order.bill_changed` `fiscal` | `Billing/BillFiscalizer` | no (1) | no (2) | no | no |
/// | `order.bill_requested` | `PosOrderService` | no (1) | yes | no | no |
/// | `order.course_ready` | `Domain/Pos/Orders/PosCourses` | no (3) | yes | no | no |
/// | `order.transferred` | `Domain/Pos/Orders/OrderHandoverService` | no (4) | yes | no | no |
/// | `kitchen.item_status_changed` | `Services/Order/KitchenService`, `PosOrderService` | yes | yes | no | no |
/// | `kitchen.order_assembled` | `Domain/Pos/Kitchen/KitchenAssembly` | yes | yes | no | no |
/// | `queue.preparing/ready/handed/canceled` | `Domain/Pos/Orders/PickupQueue` | yes | no (5) | yes | yes |
/// | `booking.changed` | `Domain/Pos/Booking/BookingService`, `BookingConfirmationService` | no | yes | no | no |
/// | `table.state_changed` | `Domain/Pos/Hall/TableStates` | no | yes | no | no |
/// | `waitlist.changed` | `Domain/Pos/Waitlist/WaitlistService` | no | no (6) | no | no |
/// | `shift.changed` | `Domain/Pos/Shifts/ShiftService` | no | no | no | no |
/// | `fiscal.receipt_updated` | `Domain/Fiscal/FiscalService`, billing/voucher/banquet providers | no | no | no | no |
/// | `menu.availability_changed` | stop list, batches, brands, daily menu | no (7) | no | no | no |
/// | `display.cart` | `Domain/Pos/Display/DisplayFeed` | no | no | no | its own till only |
/// | `bill.print_requested` | `Domain/Pos/Billing/BillPrintRequests` | no | no | no | no |
/// | `qr.*` | `Domain/Pos/QrPayments/QrPaymentService` | no | no (8) | no | no |
/// | `pos.lan_changed` | `Domain/Pos/Lan/BranchLan` | no | no | no | no |
/// | any other (a newer server) | - | yes | yes | no | no |
///
/// Why each omission is safe:
/// 1. A ticket shows lines, their kitchen state, table and times; bills, payments and receipts are not on it. When a
///    payment closes the order, `OrderCloser` moves the status (`order.status_changed`, read).
/// 2. The floor shows a place's state (free, busy, bill printed, overdue, reserved), its orders' totals, guests, waiter,
///    unsent/ready counts and `bill_requested`. A payment changes none of them (it records payments and bumps the
///    order's version; `bill_printed` comes with `printed`); a payment that settles the order closes it in the same
///    flow (`OrderCloser::closeIfSettled` -> `order.status_changed`), and the fiscal answer that closes it too. So
///    `payment` and `fiscal` bill changes are ignored on floors.
/// 3. `order.course_ready` follows the kitchen's own `kitchen.item_status_changed` (read).
/// 4. A hand-over changes the waiter only, which a ticket does not show (a move to another table is `items_changed`).
/// 5. The floor has no pickup numbers; a queue order's status moves come as `order.status_changed`.
/// 6. Only the hostess list shows the waitlist (its own filter).
/// 7. The KDS kazan panel listens for `menu.availability_changed` on its own (it reads batches, not tickets).
/// 8. A QR payment is a payment (2); the order pages and the billing page read on their order's events.
///
/// The open order and billing pages read on their own order's events only (any name), the other till and phone lists
/// keep their own filters.
abstract final class ScreenRelevance {
  /// The KDS board.
  static bool kitchen(String name) {
    if (name.startsWith('queue.')) return true;
    return switch (name) {
      'order.created' ||
      'order.status_changed' ||
      'order.assigned' ||
      'order.items_changed' ||
      'kitchen.item_status_changed' ||
      'kitchen.order_assembled' => true,
      _ => !_known(name),
    };
  }

  /// The cashier floor and the waiter's rooms. [data]: the event body (`change` of `order.bill_changed`).
  static bool floor(String name, Map<String, Object?> data) {
    if (name == 'order.bill_changed') {
      final Object? change = data['change'];
      return change != 'payment' && change != 'fiscal';
    }
    if (name.startsWith('order.')) return true;
    return switch (name) {
      'kitchen.item_status_changed' ||
      'kitchen.order_assembled' ||
      'booking.changed' ||
      'table.state_changed' => true,
      _ => !_known(name),
    };
  }

  /// The till's pickup board.
  static bool pickup(String name) =>
      name.startsWith('queue.') ||
      name == 'order.created' ||
      name == 'order.status_changed' ||
      name == 'order.assigned';

  /// The customer display: its own till's cart ([till] `null`: not known yet, every cart) and the queue.
  static bool display(String name, Map<String, Object?> data, String? till) {
    if (name.startsWith('queue.')) return true;
    if (name != 'display.cart') return false;
    final Object? terminal = data['terminal'];
    return till == null || terminal is! String || terminal == till;
  }

  /// Every event name the branch channel carries today.
  static bool _known(String name) => const <String>{
    'order.created',
    'order.status_changed',
    'order.assigned',
    'order.items_changed',
    'order.bill_changed',
    'order.bill_requested',
    'order.course_ready',
    'order.transferred',
    'kitchen.item_status_changed',
    'kitchen.order_assembled',
    'queue.preparing',
    'queue.ready',
    'queue.handed',
    'queue.canceled',
    'qr.paid',
    'qr.cancelled',
    'qr.expired',
    'qr.pending',
    'bill.print_requested',
    'booking.changed',
    'table.state_changed',
    'waitlist.changed',
    'shift.changed',
    'fiscal.receipt_updated',
    'menu.availability_changed',
    'display.cart',
    'pos.lan_changed',
  }.contains(name);
}
