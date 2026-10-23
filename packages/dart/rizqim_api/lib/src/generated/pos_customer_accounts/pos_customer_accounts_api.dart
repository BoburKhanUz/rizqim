// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_customer_accounts_debtors_response.dart';
import '../models/get_v1_pos_customer_accounts_response.dart';
import '../models/get_v1_pos_customer_accounts_uuid_response.dart';
import '../models/get_v1_pos_customer_accounts_uuid_statement_response.dart';
import '../models/patch_v1_pos_customer_accounts_uuid_response.dart';
import '../models/post_v1_pos_customer_accounts_response.dart';
import '../models/post_v1_pos_customer_accounts_uuid_adjustments_response.dart';
import '../models/post_v1_pos_customer_accounts_uuid_repayments_response.dart';
import '../models/v1_pos_customer_accounts_request_body.dart';
import '../models/v1_pos_customer_accounts_uuid_adjustments_request_body.dart';
import '../models/v1_pos_customer_accounts_uuid_repayments_request_body.dart';
import '../models/v1_pos_customer_accounts_uuid_request_body.dart';

part 'pos_customer_accounts_api.g.dart';

@RestApi()
abstract class PosCustomerAccountsApi {
  factory PosCustomerAccountsApi(Dio dio, {String? baseUrl}) =
      _PosCustomerAccountsApi;

  /// Customer accounts.
  ///
  /// Search by `q` (name, group or phone digits); `debtors=1`: only those who owe, biggest debt first. Paginated.
  /// (`per_page` up to 100).
  ///
  /// [q] - Name, group name or phone digits.
  ///
  /// [debtors] - Only accounts with a debt.
  ///
  /// [perPage] - Up to 100 (default 30).
  @GET('/v1/pos/customer-accounts')
  Future<GetV1PosCustomerAccountsResponse> customerAccountIndex({
    @Query('q') String? q,
    @Query('debtors') bool? debtors,
    @Query('per_page') int? perPage,
  });

  /// Open a customer account.
  ///
  /// `name` required; `phone` (+998…, unique in the store), `group_name`, `credit_limit` (null: no limit; 0: no credit),.
  /// `note`, `branch_id` (only that branch; default: store-wide, a branch-bound manager's own branch). Needs.
  /// customer_accounts.manage; P3-26: a cashier at the till (pos.take_payment) sends a manager's one-time.
  /// `approval_token` for customer_accounts.manage (POST /pos/approvals; 403 `approval_required` without).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/customer-accounts')
  Future<PostV1PosCustomerAccountsResponse> customerAccountStore({
    @Body() required V1PosCustomerAccountsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Debtors.
  ///
  /// Everyone who owes, biggest debt first (up to 500), and the total debt.
  @GET('/v1/pos/customer-accounts/debtors')
  Future<GetV1PosCustomerAccountsDebtorsResponse> customerAccountDebtors();

  /// A customer account, with its last 20 entries
  @GET('/v1/pos/customer-accounts/{uuid}')
  Future<GetV1PosCustomerAccountsUuidResponse> customerAccountShow({
    @Path('uuid') required String uuid,
  });

  /// Change a customer account.
  ///
  /// Only the fields sent change; `is_blocked: true` stops new credit (repayments still work). Needs.
  /// customer_accounts.manage; P3-26: a cashier (pos.take_payment) sends a manager's one-time `approval_token` for it.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PATCH('/v1/pos/customer-accounts/{uuid}')
  Future<PatchV1PosCustomerAccountsUuidResponse> customerAccountUpdate({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1PosCustomerAccountsUuidRequestBody? body,
  });

  /// Correct the debt.
  ///
  /// `amount` > 0 adds to the debt, < 0 takes from it (a write-off); `note` required. The debt never goes below 0.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/customer-accounts/{uuid}/adjustments')
  Future<PostV1PosCustomerAccountsUuidAdjustmentsResponse>
  customerAccountAdjust({
    @Path('uuid') required String uuid,
    @Body() required V1PosCustomerAccountsUuidAdjustmentsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Take a repayment.
  ///
  /// `amount` (at most the debt), `tender` cash|card|qr (`card_type`, `transaction_id`), `note`. Issues a CREDIT.
  /// receipt (`fiscal_receipt_uuid`); the owner is told on Telegram. The `Idempotency-Key` header is required.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/customer-accounts/{uuid}/repayments')
  Future<PostV1PosCustomerAccountsUuidRepaymentsResponse> customerAccountRepay({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosCustomerAccountsUuidRepaymentsRequestBody body,
  });

  /// Account statement.
  ///
  /// The entries between `from` and `to` (dates in the store's time zone, inclusive; default: the last 30 days), with.
  /// the debt before and after and the totals by type.
  ///
  /// [from] - First day, Y-m-d.
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  @GET('/v1/pos/customer-accounts/{uuid}/statement')
  Future<GetV1PosCustomerAccountsUuidStatementResponse>
  customerAccountStatement({
    @Path('uuid') required String uuid,
    @Query('from') String? from,
    @Query('to') String? to,
  });
}
