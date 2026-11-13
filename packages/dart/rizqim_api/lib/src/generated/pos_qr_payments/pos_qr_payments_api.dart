// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_pos_qr_uuid_response.dart';
import '../models/get_v1_pos_bills_uuid_qr_response.dart';
import '../models/get_v1_pos_qr_uuid_response.dart';
import '../models/post_v1_pos_bills_uuid_qr_response.dart';
import '../models/post_v1_pos_qr_uuid_simulate_paid_response.dart';
import '../models/v1_pos_bills_uuid_qr_request_body.dart';

part 'pos_qr_payments_api.g.dart';

@RestApi()
abstract class PosQrPaymentsApi {
  factory PosQrPaymentsApi(Dio dio, {String? baseUrl}) = _PosQrPaymentsApi;

  /// A bill's QR requests.
  ///
  /// The bill's QR requests, newest first (`pending` is the one to show; at most one). Needs orders.view.
  @GET('/v1/pos/bills/{uuid}/qr')
  Future<GetV1PosBillsUuidQrResponse> posQrPaymentIndex({
    @Path('uuid') required String uuid,
  });

  /// Show a QR for the bill.
  ///
  /// Makes a dynamic QR for what the bill still owes (or `amount` of it, the QR part of a mixed payment): `provider`.
  /// click, payme or uzqr (default: the branch's default provider). Print `payload` on the bill and send `display` to.
  /// the customer display (PosDisplayPayment.qr). One pending QR per bill: a new one cancels the old. The provider.
  /// confirms the payment by itself (no cashier action): the bill takes a `qr` payment (`reference` = the provider's.
  /// transaction id) and its sale receipt is issued by Rizqim (the provider registers none). Valid for the branch's.
  /// `expiry_minutes`. Needs the `qr_payments` module (403 module_disabled), pos.take_payment and an open shift (409.
  /// `shift_not_open`); 422 `qr_provider_disabled` (the branch does not offer it), 409 `provider_not_configured` (the.
  /// store's own Click/Payme merchant account, or the UzQR gateway, is not set up), 422 `overpayment` (`due`),.
  /// `mxik_missing`; 409 `bill_paid`, `bill_void`, `bill_awaiting_fiscal`, `order_changed`, `bill_changed`. Offline.
  /// the till cannot make one: it offers the static QR confirmed by hand.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/bills/{uuid}/qr')
  Future<PostV1PosBillsUuidQrResponse> posQrPaymentStore({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosBillsUuidQrRequestBody? body,
  });

  /// A QR request.
  ///
  /// Its status now (the till's poll when it has no realtime): `pending`, `paid`, `cancelled` (`cancel_reason`:.
  /// cashier, replaced, bill_changed, shift_closed, payment_refused:<code>, provider_cancelled, payme_cancel:<n>),.
  /// `expired` or `refunded`. Reading it expires it when its time ran out; a pending UzQR asks its gateway. Needs.
  /// orders.view.
  @GET('/v1/pos/qr/{uuid}')
  Future<GetV1PosQrUuidResponse> posQrPaymentShow({
    @Path('uuid') required String uuid,
  });

  /// Cancel a QR.
  ///
  /// The cashier takes the QR back (the customer pays another way): its later confirmation is refused, so the provider.
  /// does not take the money. Already cancelled or expired: returned as it is; paid: 409 `qr_already_paid` (refund.
  /// it instead). Optional `reason` (audited). Needs pos.take_payment.
  ///
  /// [reason] - Why it was cancelled (audited; up to 200 characters).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @DELETE('/v1/pos/qr/{uuid}')
  Future<DeleteV1PosQrUuidResponse> posQrPaymentDestroy({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Query('reason') String? reason,
  });

  /// Confirm a sandbox QR (demo).
  ///
  /// Plays the provider's confirmation for a QR of a sandbox driver (`simulated: true`; demo and e2e stands):.
  /// exactly what a real webhook does. A real provider's QR: 409 `qr_not_simulated`; switched off (production): 404.
  /// Needs pos.take_payment.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/qr/{uuid}/simulate-paid')
  Future<PostV1PosQrUuidSimulatePaidResponse> posQrPaymentSimulatePaid({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
  });
}
