// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_customer_accounts_members_lookup_response.dart';
import '../models/get_v1_pos_customer_accounts_uuid_corporate_statement_response.dart';
import '../models/get_v1_pos_customer_accounts_uuid_members_response.dart';
import '../models/patch_v1_pos_customer_accounts_uuid_members_member_response.dart';
import '../models/post_v1_pos_customer_accounts_uuid_members_bulk_response.dart';
import '../models/post_v1_pos_customer_accounts_uuid_members_response.dart';
import '../models/v1_pos_customer_accounts_uuid_members_bulk_request_body.dart';
import '../models/v1_pos_customer_accounts_uuid_members_member_request_body.dart';
import '../models/v1_pos_customer_accounts_uuid_members_request_body.dart';

part 'pos_corporate_accounts_api.g.dart';

@RestApi()
abstract class PosCorporateAccountsApi {
  factory PosCorporateAccountsApi(Dio dio, {String? baseUrl}) =
      _PosCorporateAccountsApi;

  /// Find a member at the till.
  ///
  /// By the talon's `code` (scanned or typed) or the member's `phone`: the member, their company's account and what.
  /// is left today (the branch's business day) and this month, for the cashier to see before charging. A member of.
  /// another store, or of an account of another branch: 404 `member_not_found`. Needs customer_accounts.manage or.
  /// pos.take_payment.
  ///
  /// [code] - The talon code (QR or badge).
  ///
  /// [phone] - The member's phone, when there is no talon.
  @GET('/v1/pos/customer-accounts/members/lookup')
  Future<GetV1PosCustomerAccountsMembersLookupResponse> corporateAccountLookup({
    @Query('code') String? code,
    @Query('phone') String? phone,
  });

  /// Monthly statement of a corporate account.
  ///
  /// `month` (YYYY-MM; default: this month in the store's time zone): what each member ate on each business day.
  /// (net of refunds; `meals` = bills), charges without a member in a row of their own (`member` null), the totals by.
  /// day and in all, the repayments received in the month and the debt now. Needs reports.view.
  ///
  /// [month] - YYYY-MM; default: the current month.
  @GET('/v1/pos/customer-accounts/{uuid}/corporate-statement')
  Future<GetV1PosCustomerAccountsUuidCorporateStatementResponse>
  corporateAccountStatement({
    @Path('uuid') required String uuid,
    @Query('month') String? month,
  });

  /// Monthly statement as Excel.
  ///
  /// The statement of GET .../corporate-statement as an .xlsx file: sheet "Hisobot" (a member per row, a business day.
  /// per column, the totals), sheet "Hisob-faktura" (the company, the month, the totals, the debt) — openspout, texts.
  /// as text. Audited (customer_account.statement_exported). Needs reports.view.
  ///
  /// [month] - YYYY-MM; default: the current month.
  @GET('/v1/pos/customer-accounts/{uuid}/corporate-statement/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> corporateAccountExport({
    @Path('uuid') required String uuid,
    @Query('month') String? month,
  });

  /// Members of a corporate account.
  ///
  /// Everyone of the company (active first, then by name), with their talon code and limits. `q`: name, code or phone.
  /// digits; `active`: only active (1) or inactive (0) ones.
  ///
  /// [q] - Name, talon code or phone digits.
  ///
  /// [active] - Only active (true) or deactivated (false) members.
  @GET('/v1/pos/customer-accounts/{uuid}/members')
  Future<GetV1PosCustomerAccountsUuidMembersResponse> corporateAccountMembers({
    @Path('uuid') required String uuid,
    @Query('q') String? q,
    @Query('active') bool? active,
  });

  /// Add a member.
  ///
  /// `name` required; `phone` (+998…, unique among the store's members), `code` (the talon: 4–32 letters, digits.
  /// or "-"; default: ten random digits; unique in the store), `daily_limit` (sum per business day; null: none),.
  /// `meals_per_day` (subscription; null: none), `note`. Needs customer_accounts.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/customer-accounts/{uuid}/members')
  Future<PostV1PosCustomerAccountsUuidMembersResponse>
  corporateAccountStoreMember({
    @Path('uuid') required String uuid,
    @Body() required V1PosCustomerAccountsUuidMembersRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Add members from pasted lines.
  ///
  /// `lines`: one member per line, "name;phone" (the phone may be empty; a third column, when given, is the talon code).
  /// `daily_limit` and `meals_per_day` apply to each. Up to 500 lines; blank lines are skipped. All or nothing: any bad line.
  /// (no name, a wrong phone, a phone or code already in the store or twice in the paste) answers 422 `bulk_invalid`.
  /// with `lines: [{line, reason, message}]` and adds no one. Needs customer_accounts.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/customer-accounts/{uuid}/members/bulk')
  Future<PostV1PosCustomerAccountsUuidMembersBulkResponse>
  corporateAccountBulk({
    @Path('uuid') required String uuid,
    @Body() required V1PosCustomerAccountsUuidMembersBulkRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Change a member.
  ///
  /// Only the fields sent change; `is_active: false` stops their talon (the till refuses it: `member_inactive`);.
  /// `regenerate_code: true` gives them a new talon code (the old chit stops working). Needs customer_accounts.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PATCH('/v1/pos/customer-accounts/{uuid}/members/{member}')
  Future<PatchV1PosCustomerAccountsUuidMembersMemberResponse>
  corporateAccountUpdateMember({
    @Path('uuid') required String uuid,
    @Path('member') required String member,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1PosCustomerAccountsUuidMembersMemberRequestBody? body,
  });
}
