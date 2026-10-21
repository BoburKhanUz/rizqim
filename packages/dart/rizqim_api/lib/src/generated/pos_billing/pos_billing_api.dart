// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_orders_uuid_bills_response.dart';
import '../models/get_v1_pos_shifts_current_response.dart';
import '../models/get_v1_pos_shifts_response.dart';
import '../models/get_v1_pos_shifts_uuid_response.dart';
import '../models/get_v1_pos_shifts_uuid_x_report_response.dart';
import '../models/post_v1_pos_approvals_response.dart';
import '../models/post_v1_pos_bills_uuid_fiscal_retry_response.dart';
import '../models/post_v1_pos_bills_uuid_payments_response.dart';
import '../models/post_v1_pos_bills_uuid_print_response.dart';
import '../models/post_v1_pos_orders_uuid_bills_response.dart';
import '../models/post_v1_pos_orders_uuid_close_response.dart';
import '../models/post_v1_pos_refunds_response.dart';
import '../models/post_v1_pos_shifts_response.dart';
import '../models/post_v1_pos_shifts_uuid_cash_movements_response.dart';
import '../models/post_v1_pos_shifts_uuid_close_response.dart';
import '../models/v1_pos_approvals_request_body.dart';
import '../models/v1_pos_bills_uuid_payments_request_body.dart';
import '../models/v1_pos_bills_uuid_print_request_body.dart';
import '../models/v1_pos_orders_uuid_bills_request_body.dart';
import '../models/v1_pos_refunds_request_body.dart';
import '../models/v1_pos_shifts_request_body.dart';
import '../models/v1_pos_shifts_uuid_cash_movements_request_body.dart';
import '../models/v1_pos_shifts_uuid_close_request_body.dart';

part 'pos_billing_api.g.dart';

@RestApi()
abstract class PosBillingApi {
  factory PosBillingApi(Dio dio, {String? baseUrl}) = _PosBillingApi;

  /// Manager approval (PIN on the terminal).
  ///
  /// The staff member signed in on the terminal (a staff PIN session) lacks a permission for an action (a void after.
  /// the bill, a discount, a reprint, a refund, cash out, nasiya...): a manager types their own staff id (or staff.
  /// code) and PIN on that terminal. The answer is a one-time `token` for that one `permission` on that terminal,.
  /// valid 60 seconds: send it as `approval_token` with the action. The PIN check and lockout are the sign-in's.
  /// (wrong PIN 422 `pin_invalid`, locked 429 `pin_locked` with Retry-After); a manager without the permission 403.
  /// `approver_not_allowed`. Only from a staff PIN session on a terminal (422 `terminal_required` otherwise). Both.
  /// people are in the audit log (`pos.approval_granted`, and `pos.approval_used` when the action consumes it).
  @POST('/v1/pos/approvals')
  Future<PostV1PosApprovalsResponse> posApprovalStore({
    @Body() required V1PosApprovalsRequestBody body,
  });

  /// Ask again for a bill's receipt.
  ///
  /// For a bill whose payments cover it but whose sale receipt is not issued ("fiskal kutmoqda": pending or failed):.
  /// asks the fiscal provider again (same receipt, never a second one); issued: the bill is paid and the order closed.
  /// when every bill is. Needs pos.take_payment.
  @POST('/v1/pos/bills/{uuid}/fiscal-retry')
  Future<PostV1PosBillsUuidFiscalRetryResponse> posBillFiscalRetry({
    @Path('uuid') required String uuid,
  });

  /// Take payment for a bill.
  ///
  /// One or more `tenders` (a mixed payment), each with the `amount` it pays of the bill: `cash` (optional.
  /// `tendered_amount`: what the guest gave, the change is worked out), `card` (a separate bank terminal:.
  /// `card_type` — uzcard, humo, visa, mastercard, unionpay, mir, other — and the terminal's `transaction_id` are.
  /// required), `qr` (optional `reference`), `prepayment` (the booking's deposit, at most what is left of it, once.
  /// per bill), `account` (nasiya: `account_uuid`; needs customer_accounts.manage or a manager's `approval_token`;.
  /// once per bill). Payments may come in several requests; together they never exceed the bill (422 `overpayment`).
  /// The money lands in the open shift of the terminal (or `shift_uuid`; 409 `shift_not_open`). When the bill is.
  /// covered, its sale receipt is asked for: the bill is `paid` once the receipt is issued (`fiscal.status`), and the.
  /// order is closed (delivered, paid, table free) once all its bills are. A receipt not issued yet leaves the bill.
  /// "fiskal kutmoqda" (`fiscal.status` pending or failed): retry with `.../fiscal-retry`. A line without MXIK: 422.
  /// `mxik_missing` before any money is taken. Optional `version`. Needs pos.take_payment.
  @POST('/v1/pos/bills/{uuid}/payments')
  Future<PostV1PosBillsUuidPaymentsResponse> posBillPay({
    @Path('uuid') required String uuid,
    @Body() required V1PosBillsUuidPaymentsRequestBody body,
  });

  /// Print the bill.
  ///
  /// Returns the bill to print ("Bu fiskal chek emas" is the template's). The first print hands it to the guest and.
  /// locks the order (`bill_locked_at`: voids and discounts need a manager from then on); printing again is a copy:.
  /// `copy: true`, print "NUSXA"; it needs pos.reprint_bill or a manager's `approval_token` (403 `approval_required`).
  /// An open bill made before the order changed: 409 `order_changed` (bill again). Optional `version` (409 when.
  /// stale). Needs orders.manage or pos.take_payment.
  @POST('/v1/pos/bills/{uuid}/print')
  Future<PostV1PosBillsUuidPrintResponse> posBillPrint({
    @Path('uuid') required String uuid,
    @Body() V1PosBillsUuidPrintRequestBody? body,
  });

  /// An order's bills.
  ///
  /// The order's billing state (`billed_amount`, `paid_amount`, `unbilled_amount`: what no bill carries yet, e.g. a.
  /// round added after the bill was printed; `fiscal_pending`: a bill is paid but its receipt is not issued yet,.
  /// "fiskal kutmoqda"; `bills_out_of_date`: the order changed after its open bills were made: bill again), its bills.
  /// (void ones only with `all=1`) with their lines, charges, per-guest amount and payments, and its refunds. Needs.
  /// orders.view in the order's branch.
  ///
  /// [all] - Include voided bills.
  @GET('/v1/pos/orders/{uuid}/bills')
  Future<GetV1PosOrdersUuidBillsResponse> posBillIndex({
    @Path('uuid') required String uuid,
    @Query('all') bool? all,
  });

  /// Make or split the bill.
  ///
  /// Bills what is left to bill of the order (bills already being paid stay; other open or printed bills are voided.
  /// and made again). `mode`: `single` (one bill), `equal` (`parts` bills with the same total; the remainder tiyin on.
  /// the first), `items` (`bills`: each with its `items` — `order_item_id` and `quantity`, fractions too, e.g. 0.5 of.
  /// 1.5 kg; every line left must be given out in full; optional `guests` for the per-guest amount). The discount and.
  /// the charges (room fee, minimum spend top-up, service charge, place fee) are shared in proportion; all shares are.
  /// exact to the tiyin. `version`: the order's (409 `version_conflict` when stale). One bill: orders.manage or.
  /// pos.take_payment; splitting: pos.take_payment (403 `permission_denied`). 409 `nothing_to_bill`, `order_closed`,.
  /// `not_pos_order`; 422 `items_not_covered`, `bill_empty`, `item_not_billable`, `parts_invalid`.
  @POST('/v1/pos/orders/{uuid}/bills')
  Future<PostV1PosOrdersUuidBillsResponse> posBillStore({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidBillsRequestBody body,
  });

  /// Close a paid order.
  ///
  /// Closes the order when everything on it is on paid bills (receipts issued): delivered, paid, table free. Payments.
  /// close it by themselves; this is for a close that was interrupted, and asks again for receipts still pending.
  /// first. Calling it on a closed order returns it. Not settled yet: 409 `order_not_settled` (with what is left).
  /// Needs pos.take_payment.
  @POST('/v1/pos/orders/{uuid}/close')
  Future<PostV1PosOrdersUuidCloseResponse> posBillClose({
    @Path('uuid') required String uuid,
  });

  /// Refund a payment within the shift.
  ///
  /// Gives back `amount` of a cash, card or QR payment (`payment_uuid`) of a paid bill, taken in the open shift of.
  /// this terminal (or `shift_uuid`): 409 `refund_outside_shift` for another shift's payment, 422.
  /// `refund_tender_invalid` for a deposit or nasiya part, 422 `refund_exceeds_payment` beyond what is left of it,.
  /// 409 `bill_not_fiscalized` before the sale receipt is issued, 409 `not_enough_cash` beyond the drawer's cash.
  /// `reason` is required. Needs pos.refund, or a manager's `approval_token` for it (403 `approval_required`). Cash.
  /// leaves the drawer; a REFUND receipt naming the sale receipt is asked for (`fiscal`); the owner is told in.
  /// Telegram.
  @POST('/v1/pos/refunds')
  Future<PostV1PosRefundsResponse> posRefundStore({
    @Body() required V1PosRefundsRequestBody body,
  });

  /// Shifts of a branch.
  ///
  /// Newest first: the caller's branches (`branch_id` narrows to one), optionally by `status` and business day.
  /// range (`from`, `to`, YYYY-MM-DD), at most `limit` (50, up to 200). For the web panel's shift and Z list too.
  /// Needs pos.close_shift or reports.view.
  ///
  /// [status] - open or closed.
  ///
  /// [from] - Business day from (YYYY-MM-DD).
  ///
  /// [to] - Business day to (YYYY-MM-DD).
  @GET('/v1/pos/shifts')
  Future<GetV1PosShiftsResponse> posShiftIndex({
    @Query('branch_id') int? branchId,
    @Query('status') String? status,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('limit') int? limit,
  });

  /// Open a shift.
  ///
  /// On this terminal (a staff PIN session on a terminal paired to a branch; 422 `terminal_required` otherwise),.
  /// with the `opening_cash` in the drawer. One open shift per terminal (409 `shift_already_open`). Its business day.
  /// follows the branch's day start (05:00 by default: 02:00 still belongs to the day before). Needs pos.open_shift.
  @POST('/v1/pos/shifts')
  Future<PostV1PosShiftsResponse> posShiftStore({
    @Body() required V1PosShiftsRequestBody body,
  });

  /// This terminal's open shift.
  ///
  /// The open shift of the calling terminal with its X report so far, or `shift: null`. A normal sign-in (no.
  /// terminal) always gets null.
  @GET('/v1/pos/shifts/current')
  Future<GetV1PosShiftsCurrentResponse> posShiftCurrent();

  /// One shift (with its Z report).
  ///
  /// The shift and, once closed, its Z report as it was at closing (`summary`). Needs pos.close_shift or.
  /// reports.view.
  @GET('/v1/pos/shifts/{uuid}')
  Future<GetV1PosShiftsUuidResponse> posShiftShow({
    @Path('uuid') required String uuid,
  });

  /// Cash in or out of the drawer.
  ///
  /// `type`: `cash_in`, `cash_out`, `wage` (paid to an employee: `employee_id` required) or `purchase` (bazaar.
  /// shopping); `category` from the type's list (cash_in: change_fund, owner, other; cash_out: collection, owner,.
  /// supplies, utilities, other; wage: daily_wage, advance, bonus; purchase: bazaar, meat, bread, supplies, other);.
  /// `other` needs a `note`. Money out never exceeds the expected cash (409 `not_enough_cash`). Needs pos.cash_in_out,.
  /// or a manager's `approval_token` for it (403 `approval_required`). The shift must be open (409 `shift_closed`).
  @POST('/v1/pos/shifts/{uuid}/cash-movements')
  Future<PostV1PosShiftsUuidCashMovementsResponse> posShiftCashMovement({
    @Path('uuid') required String uuid,
    @Body() required V1PosShiftsUuidCashMovementsRequestBody body,
  });

  /// Close the shift (Z).
  ///
  /// `counted_breakdown`: the cash counted, banknote => count (200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000,.
  /// 500, 200, 100); the difference to the expected cash is kept. Refused while POS orders are open (409.
  /// `shift_has_open_orders`: the ones opened in this shift, or any of the branch when it is the last open shift) or.
  /// a sale's receipt is pending (409 `shift_has_pending_receipts`), unless `force: true` with a `reason` by someone.
  /// with orders.cancel (or a manager's `approval_token` for it): open orders then wait for the next shift. The Z.
  /// report is kept in `summary`, numbered per branch (`z_number`) and sent to the owner in Telegram. Needs.
  /// pos.close_shift.
  @POST('/v1/pos/shifts/{uuid}/close')
  Future<PostV1PosShiftsUuidCloseResponse> posShiftClose({
    @Path('uuid') required String uuid,
    @Body() required V1PosShiftsUuidCloseRequestBody body,
  });

  /// X report.
  ///
  /// The shift's report now, without closing it: takings by tender (`by_tender`, `cards` by card type), by channel,.
  /// by waiter (with the service charge their bills brought), discounts, voided lines, refunds, deposits taken and.
  /// applied, sales on credit and repayments, the drawer's movements and the expected cash. A closed shift: as at its.
  /// close. Needs pos.close_shift or reports.view.
  @GET('/v1/pos/shifts/{uuid}/x-report')
  Future<GetV1PosShiftsUuidXReportResponse> posShiftXReport({
    @Path('uuid') required String uuid,
  });
}
