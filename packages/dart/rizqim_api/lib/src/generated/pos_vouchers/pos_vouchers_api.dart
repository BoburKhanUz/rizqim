// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_pos_orders_uuid_promo_response.dart';
import '../models/get_v1_pos_vouchers_code_response.dart';
import '../models/post_v1_pos_orders_uuid_promo_response.dart';
import '../models/post_v1_pos_vouchers_sell_response.dart';
import '../models/v1_pos_orders_uuid_promo_request_body.dart';
import '../models/v1_pos_vouchers_sell_request_body.dart';

part 'pos_vouchers_api.g.dart';

@RestApi()
abstract class PosVouchersApi {
  factory PosVouchersApi(Dio dio, {String? baseUrl}) = _PosVouchersApi;

  /// Apply a promo code to a POS order.
  ///
  /// A discount voucher is a store promo code: `code` becomes the order's discount on the dishes with the reason.
  /// `promo:<CODE>` (a percentage without a cap stays a percent; a fixed amount, or a capped percentage, is worked.
  /// out now). The cashier's discount limit does not apply; the waiter rule does. One code per order: 409.
  /// `promo_already_applied` (take it off first); a discount typed by hand later replaces it. Usage counts as for.
  /// online orders (a cancelled order gives it back). 404 `promo_not_found` (unknown, another store's, or a.
  /// platform-wide code), 422 `promo_inactive`, `promo_min_order`, `promo_limit_reached`, `promo_no_discount`; 409.
  /// `paid_bill_affected`, `version_conflict`. Needs orders.manage and the `vouchers` module; online only.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/promo')
  Future<PostV1PosOrdersUuidPromoResponse> posVoucherApplyPromo({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidPromoRequestBody body,
  });

  /// Take the promo code off a POS order.
  ///
  /// The order's discount goes with it (422 `promo_not_applied` when it has none). Needs orders.manage.
  ///
  /// [version] - The order's version (409 version_conflict when stale).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @DELETE('/v1/pos/orders/{uuid}/promo')
  Future<DeleteV1PosOrdersUuidPromoResponse> posVoucherRemovePromo({
    @Path('uuid') required String uuid,
    @Query('version') required int version,
    @Header('Idempotency-Key') required String idempotencyKey,
  });

  /// Sell a gift certificate.
  ///
  /// A non-menu sale: `face_value` (whole so'm, 1 000 – 100 000 000) paid by `tender` cash, card (`card_type` and the.
  /// terminal's `transaction_id` required) or qr (optional `reference`); optional `buyer_name`, `buyer_phone`.
  /// (+998…), `valid_until` (the last business day it pays, default a year), `note`. The money lands in the open shift.
  /// of the terminal (or `shift_uuid`; 409 `shift_not_open`) — the X/Z shows it under `vouchers.sold`, apart from the.
  /// sales — and an ADVANCE receipt is issued for it (`fiscal`). The answer carries the code and its `print` block.
  /// (store, code, QR text, value, validity) for the certificate slip. 422 `face_value_invalid`, `tender_invalid`,.
  /// `card_type_required`, `transaction_id_required`, `buyer_phone_invalid`, `valid_until_invalid`, `mxik_missing`.
  /// The same Idempotency-Key returns the same certificate. Needs pos.take_payment and the `vouchers` module.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/vouchers/sell')
  Future<PostV1PosVouchersSellResponse> posVoucherSell({
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosVouchersSellRequestBody body,
  });

  /// Look up a gift certificate.
  ///
  /// By the code as printed or scanned (spaces, dashes and lower case are fine): its balance, validity and status.
  /// (`expired` once its last business day has passed), the buyer's phone masked, and its last 10 entries. Another.
  /// store's or an unknown code: 404 `voucher_not_found`. Needs pos.take_payment.
  @GET('/v1/pos/vouchers/{code}')
  Future<GetV1PosVouchersCodeResponse> posVoucherShow({
    @Path('code') required String code,
  });
}
