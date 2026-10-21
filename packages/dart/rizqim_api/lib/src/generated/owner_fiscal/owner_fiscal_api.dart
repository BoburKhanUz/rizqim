// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_fiscal_readiness_response.dart';
import '../models/get_v1_owner_fiscal_receipts_response.dart';
import '../models/get_v1_owner_fiscal_receipts_uuid_response.dart';
import '../models/get_v1_owner_fiscal_settings_response.dart';
import '../models/payment_form.dart';
import '../models/post_v1_owner_fiscal_receipts_uuid_retry_response.dart';
import '../models/put_v1_owner_fiscal_settings_response.dart';
import '../models/status5.dart';
import '../models/type6.dart';
import '../models/v1_owner_fiscal_settings_request_body.dart';

part 'owner_fiscal_api.g.dart';

@RestApi()
abstract class OwnerFiscalApi {
  factory OwnerFiscalApi(Dio dio, {String? baseUrl}) = _OwnerFiscalApi;

  /// Fiscal readiness.
  ///
  /// What blocks selling in fiscal mode: products without a valid MXIK code (they cannot be put on a POS order.
  /// while fiscal mode is on) and charges without one. menu.view or settings.manage.
  @GET('/v1/owner/fiscal/readiness')
  Future<GetV1OwnerFiscalReadinessResponse> fiscalSettingsReadiness();

  /// Fiscal receipts.
  ///
  /// Newest first, paginated. Filters: `status`, `type`, `payment_form`, `from`/`to` (days of the store's.
  /// timezone), `branch_id`, `subject_type` with `subject_id` (e.g. the receipts of one order).
  ///
  /// [status] - pending | issued | failed.
  ///
  /// [type] - sale | refund | advance | credit.
  ///
  /// [paymentForm] - cash | card | qr | mixed.
  ///
  /// [branchId] - Filial.
  ///
  /// [subjectType] - Chek nimaga tegishli: order, order_bill, booking_payment ...
  ///
  /// [subjectId] - subject_type bilan birga.
  ///
  /// [from] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [to] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [perPage] - 1–100, standart 25.
  ///
  /// [page] - Sahifa raqami, 1 dan (Laravel sahifalari).
  @GET('/v1/owner/fiscal/receipts')
  Future<GetV1OwnerFiscalReceiptsResponse> fiscalReceiptIndex({
    @Query('status') Status5? status,
    @Query('type') Type6? type,
    @Query('payment_form') PaymentForm? paymentForm,
    @Query('branch_id') int? branchId,
    @Query('subject_type') String? subjectType,
    @Query('subject_id') int? subjectId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });

  /// A fiscal receipt.
  ///
  /// Another store's receipt: 404; another branch's (for branch-bound staff): 403.
  @GET('/v1/owner/fiscal/receipts/{uuid}')
  Future<GetV1OwnerFiscalReceiptsUuidResponse> fiscalReceiptShow({
    @Path('uuid') required String uuid,
  });

  /// Retry a receipt now.
  ///
  /// settings.manage. A pending or failed receipt is sent to the provider at once (a failed one becomes pending.
  /// first); the answer is the receipt afterwards. An issued receipt, or one recorded from Payme/Click: 422.
  @POST('/v1/owner/fiscal/receipts/{uuid}/retry')
  Future<PostV1OwnerFiscalReceiptsUuidRetryResponse> fiscalReceiptRetry({
    @Path('uuid') required String uuid,
  });

  /// Fiscal settings.
  ///
  /// The store's TIN and tax regime, and its fiscal settings with every default filled in. `active_driver` is where.
  /// receipts go now (`null` while fiscal mode is off: receipts are recorded as "fiskal emas").
  @GET('/v1/owner/fiscal/settings')
  Future<GetV1OwnerFiscalSettingsResponse> fiscalSettingsShow();

  /// Update fiscal settings.
  ///
  /// Each field is optional (left out = kept). `tin` 9 digits; `tax_regime` vat (12%) | vat_simplified (6%) |.
  /// turnover (no VAT). `fiscal_settings.charges.<charge>` sets the receipt line of a charge (room_fee, place_fee,.
  /// min_spend_topup, service_charge, deposit, booking_fee, credit, delivery): `mxik` 17 digits, `package_code`.
  /// digits; a null field goes back to the default. `deposit_refund_receipt` / `retained_deposit_receipt`: whether a.
  /// booking deposit given back / kept gets a receipt (null: the default). Turning `enabled` on needs the TIN and.
  /// the tax regime.
  @PUT('/v1/owner/fiscal/settings')
  Future<PutV1OwnerFiscalSettingsResponse> fiscalSettingsUpdate({
    @Body() V1OwnerFiscalSettingsRequestBody? body,
  });
}
