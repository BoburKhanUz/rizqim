// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_qr_payments_response.dart';
import '../models/put_v1_owner_pos_qr_payments_response.dart';
import '../models/v1_owner_pos_qr_payments_request_body.dart';

part 'owner_qr_payments_api.g.dart';

@RestApi()
abstract class OwnerQrPaymentsApi {
  factory OwnerQrPaymentsApi(Dio dio, {String? baseUrl}) = _OwnerQrPaymentsApi;

  /// The branch's QR payment settings.
  ///
  /// `providers`: offered at the till (each also needs its merchant account: `merchants[].configured` — Click and.
  /// Payme: the store's own merchant account with its keys in the payment settings and "o'z hisobi" on; UzQR: a gateway.
  /// in the fiscal settings, `fiscal_settings.uzqr.driver`); `default_provider`; `expiry_minutes` (1–30, default 5);.
  /// `enabled`: the branch has the `qr_payments` module.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  @GET('/v1/owner/pos/qr-payments')
  Future<GetV1OwnerPosQrPaymentsResponse> qrPaymentSettingsShow({
    @Query('branch_id') int? branchId,
  });

  /// Change the branch's QR payment settings.
  ///
  /// Send what changes: `providers` (a list of click, payme, uzqr; may be empty), `default_provider` (one of them, or.
  /// null), `expiry_minutes` (1–30). Needs the `qr_payments` module (403 module_disabled); a locked subscription.
  /// changes nothing (403 subscription_locked). Audited (settings.qr_payments_changed).
  @PUT('/v1/owner/pos/qr-payments')
  Future<PutV1OwnerPosQrPaymentsResponse> qrPaymentSettingsUpdate({
    @Body() V1OwnerPosQrPaymentsRequestBody? body,
  });
}
