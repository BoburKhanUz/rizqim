/// Rizqim POS ma'lumot qatlami (Flutter'siz, `dart test`): kassa va ofitsiant ilovalari backend bilan shu paket orqali
/// ishlaydi.
///
/// * [PosData] — hamma narsa bitta [RizqimClient] ustida: [PosSession] (terminal + xodim PIN sessiyasi, ruxsatlar,
///   filial, bootstrap, smena), [ApprovalService] (menejer tasdig'i), repozitoriylar ([PosBootstrapRepository],
///   [PosCatalogRepository], [FloorRepository], [PosOrderRepository], [BillingRepository], [ShiftRepository],
///   [BookingRepository], [CustomerAccountRepository]).
/// * Har o'zgartiruvchi so'rov `Idempotency-Key` bilan; xatolar turi bo'yicha ([PosException] va avlodlari:
///   [PosVersionConflict], [PosApprovalRequired], [PosConflict], [PosRejected], ...) o'zbekcha matn bilan.
/// * Pul — `pos_core` [PosMoney] (tiyin), miqdor — [PosQuantity].
/// * [PosRealtime] — `private-pos-branch.{id}` kanali (terminal tokeni bilan), [PosEvent] lar va
///   [PosRefreshScheduler] (polling zaxirasi).
/// * [PosOffline] — internetsiz cheklangan rejim (P3-31, `pos_sync` ustida): navbat, lokal ko'rinish, sinxronlash.
/// * Testlar uchun soxta backend: `package:pos_data/testing.dart`.
library;

// P3-31: the offline queue, its status and limits (the cashier works with them through PosOffline)
export 'package:pos_sync/pos_sync.dart';

export 'src/accounts/account_models.dart';
export 'src/accounts/account_repository.dart';
export 'src/billing/billing_models.dart';
export 'src/billing/billing_repository.dart';
export 'src/bookings/booking_models.dart';
export 'src/bookings/booking_repository.dart';
export 'src/bootstrap/bootstrap_models.dart';
export 'src/bootstrap/bootstrap_repository.dart';
export 'src/catalog/catalog_models.dart';
export 'src/catalog/catalog_repository.dart';
export 'src/catalog/quick_items.dart';
export 'src/display/display_feed.dart';
export 'src/display/display_models.dart';
export 'src/display/display_repository.dart';
export 'src/entitlements/entitlements_models.dart';
export 'src/fiscal/fiscal_receipt_models.dart';
export 'src/format/format_models.dart';
export 'src/fiscal/fiscal_receipt_repository.dart';
export 'src/floor/floor_models.dart';
export 'src/floor/floor_repository.dart';
export 'src/hostess/hostess_models.dart';
export 'src/hostess/hostess_repository.dart';
export 'src/http/connectivity.dart';
export 'src/http/pos_exceptions.dart';
export 'src/http/pos_http.dart';
export 'src/json.dart';
export 'src/menu_day/batch_repository.dart';
export 'src/menu_day/menu_day_models.dart';
export 'src/offline/offline_book.dart';
export 'src/offline/offline_pricing.dart';
export 'src/offline/offline_settings.dart';
export 'src/offline/pos_offline.dart';
export 'src/offline/sync_models.dart';
export 'src/orders/course_models.dart';
export 'src/orders/order_models.dart';
export 'src/orders/order_repository.dart';
export 'src/pickup/queue_number_models.dart';
export 'src/pickup/queue_number_repository.dart';
export 'src/pos_data.dart';
export 'src/queue/online_order_models.dart';
export 'src/queue/online_order_repository.dart';
export 'src/realtime/pos_events.dart';
export 'src/realtime/pos_realtime.dart';
export 'src/session/approvals.dart';
export 'src/session/pos_session.dart';
export 'src/shift/shift_models.dart';
export 'src/shift/shift_repository.dart';
export 'src/staffpay/staff_pay_models.dart';
export 'src/staffpay/staff_pay_repository.dart';
export 'src/terminal/terminal_flow_models.dart';
export 'src/tips/tip_models.dart';
export 'src/tips/tip_repository.dart';
