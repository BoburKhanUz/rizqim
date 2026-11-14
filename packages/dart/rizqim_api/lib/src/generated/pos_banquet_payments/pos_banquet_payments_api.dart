// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_banquets_uuid_cancellation_response.dart';
import '../models/get_v1_pos_banquets_uuid_payments_response.dart';
import '../models/post_v1_pos_banquets_uuid_cancellation_response.dart';
import '../models/post_v1_pos_banquets_uuid_payments_row_bank_transfer_response.dart';
import '../models/post_v1_pos_banquets_uuid_payments_row_pay_response.dart';
import '../models/put_v1_pos_banquets_uuid_payments_response.dart';
import '../models/v1_pos_banquets_uuid_cancellation_request_body.dart';
import '../models/v1_pos_banquets_uuid_payments_request_body.dart';
import '../models/v1_pos_banquets_uuid_payments_row_bank_transfer_request_body.dart';
import '../models/v1_pos_banquets_uuid_payments_row_pay_request_body.dart';

part 'pos_banquet_payments_api.g.dart';

@RestApi()
abstract class PosBanquetPaymentsApi {
  factory PosBanquetPaymentsApi(Dio dio, {String? baseUrl}) =
      _PosBanquetPaymentsApi;

  /// What cancelling the event would do.
  ///
  /// The days left before the event, the rule band's retained %, what was paid, the retained and refunded parts and,.
  /// per paid row, how its refund goes back (`cash_drawer`, `card`, `qr`, `bank_transfer`). `retained`: preview a.
  /// manager's own retained amount (0 … paid). Needs banquets.manage.
  ///
  /// [retained] - A manager's own retained amount to preview (0 … paid).
  @GET('/v1/pos/banquets/{uuid}/cancellation')
  Future<GetV1PosBanquetsUuidCancellationResponse> banquetPaymentQuote({
    @Path('uuid') required String uuid,
    @Query('retained') String? retained,
  });

  /// Cancel the event with its refund and retained parts.
  ///
  /// `reason` required. The rule table decides the retained part of what was paid (GET …/cancellation shows it); a.
  /// manager may send `retained` (0 … paid) with `override_reason` (422 `override_reason_required`; audited, the.
  /// owner hears it). Refunds: cash from the drawer of the open shift on this terminal (409 `refund_needs_shift`.
  /// elsewhere; pos.refund or a manager's `approval_token`), card and QR the way they were paid (REFUND receipts.
  /// against the advance receipts), a bank transfer marked "to be returned by transfer"; the retained part is a SALE.
  /// receipt of the cancellation service paid by the advance (prepayment). The unpaid rows go and the halls are free.
  /// Nothing paid: the event is just released. 409 `event_not_cancellable`. Needs banquets.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/banquets/{uuid}/cancellation')
  Future<PostV1PosBanquetsUuidCancellationResponse> banquetPaymentCancel({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosBanquetsUuidCancellationRequestBody body,
  });

  /// An event's payment schedule.
  ///
  /// The rows (deposit, interims, final: due date, amount, status, tender, ADVANCE receipt; `overdue` once its date has.
  /// passed unpaid), every money entry (payments, bank transfers, refunds, retained parts), the figures for the event.
  /// card (`summary`: paid, outstanding, overdue, next due) and, for a cancelled event, what the cancellation decided.
  @GET('/v1/pos/banquets/{uuid}/payments')
  Future<GetV1PosBanquetsUuidPaymentsResponse> banquetPaymentShow({
    @Path('uuid') required String uuid,
  });

  /// Edit the unpaid rows of the schedule.
  ///
  /// `rows`: the unpaid rows as they should be (`uuid` of an existing unpaid row keeps it; `kind` deposit, interim or.
  /// final; `due_date`; `amount`); they must add up to what is still to pay (422 `schedule_sum_mismatch` with.
  /// `expected`). Paid rows never change. `version`: the event's (409 `version_conflict`). Needs banquets.manage and.
  /// the `banquet` module; audited.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PUT('/v1/pos/banquets/{uuid}/payments')
  Future<PutV1PosBanquetsUuidPaymentsResponse> banquetPaymentUpdate({
    @Path('uuid') required String uuid,
    @Body() required V1PosBanquetsUuidPaymentsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Record a bank transfer against a row.
  ///
  /// The manager records money the customer sent by bank transfer: `reference` (the payment order number), `paid_on`.
  /// (default today, never in the future), optional `amount` (a part of the row). No shift and no receipt (the.
  /// invoice/ESF is issued outside the system); it counts in the event's paid total and is offset in the final sale.
  /// receipt as prepayment like the advances. Confirms the event like a deposit paid at the till. Needs.
  /// banquets.manage. The same Idempotency-Key returns the same entry.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/banquets/{uuid}/payments/{row}/bank-transfer')
  Future<PostV1PosBanquetsUuidPaymentsRowBankTransferResponse>
  banquetPaymentTransfer({
    @Path('uuid') required String uuid,
    @Path('row') required String row,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosBanquetsUuidPaymentsRowBankTransferRequestBody body,
  });

  /// Take a scheduled payment at the till.
  ///
  /// The row's amount (or a part: `amount`; the rest stays an unpaid row with the same date) by `tender` cash, card.
  /// (`card_type` and the terminal's `transaction_id` required) or qr (a static QR; optional `reference`). The money.
  /// lands in the open shift of the terminal (or `shift_uuid`; 409 `shift_not_open`) — the X/Z shows it under.
  /// `banquets.taken` — and an ADVANCE receipt is issued at once (`fiscal`). The event becomes `confirmed` once its.
  /// deposit row is paid in full. 409 `payment_not_due`, `event_not_payable`; 422 `amount_exceeds_row`,.
  /// `tender_invalid`, `card_type_required`, `transaction_id_required`, `mxik_missing`. Needs pos.take_payment. The.
  /// same Idempotency-Key returns the same payment.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/banquets/{uuid}/payments/{row}/pay')
  Future<PostV1PosBanquetsUuidPaymentsRowPayResponse> banquetPaymentPay({
    @Path('uuid') required String uuid,
    @Path('row') required String row,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosBanquetsUuidPaymentsRowPayRequestBody body,
  });
}
