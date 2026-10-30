// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_staff_pay_rules_uuid_response.dart';
import '../models/delete_v1_owner_staff_pay_statements_uuid_response.dart';
import '../models/delete_v1_owner_staff_pay_work_sessions_uuid_response.dart';
import '../models/get_v1_owner_staff_pay_rules_response.dart';
import '../models/get_v1_owner_staff_pay_statements_response.dart';
import '../models/get_v1_owner_staff_pay_statements_uuid_response.dart';
import '../models/get_v1_owner_staff_pay_work_sessions_response.dart';
import '../models/post_v1_owner_staff_pay_rules_response.dart';
import '../models/post_v1_owner_staff_pay_statements_response.dart';
import '../models/post_v1_owner_staff_pay_statements_uuid_close_response.dart';
import '../models/post_v1_owner_staff_pay_statements_uuid_lines_line_pay_response.dart';
import '../models/post_v1_owner_staff_pay_statements_uuid_recompute_response.dart';
import '../models/post_v1_owner_staff_pay_statements_uuid_reopen_response.dart';
import '../models/post_v1_owner_staff_pay_work_sessions_response.dart';
import '../models/put_v1_owner_staff_pay_rules_uuid_response.dart';
import '../models/put_v1_owner_staff_pay_settings_response.dart';
import '../models/put_v1_owner_staff_pay_statements_uuid_lines_line_response.dart';
import '../models/put_v1_owner_staff_pay_work_sessions_uuid_response.dart';
import '../models/v1_owner_staff_pay_rules_request_body.dart';
import '../models/v1_owner_staff_pay_rules_uuid_request_body.dart';
import '../models/v1_owner_staff_pay_settings_request_body.dart';
import '../models/v1_owner_staff_pay_statements_request_body.dart';
import '../models/v1_owner_staff_pay_statements_uuid_lines_line_pay_request_body.dart';
import '../models/v1_owner_staff_pay_statements_uuid_lines_line_request_body.dart';
import '../models/v1_owner_staff_pay_statements_uuid_reopen_request_body.dart';
import '../models/v1_owner_staff_pay_work_sessions_request_body.dart';
import '../models/v1_owner_staff_pay_work_sessions_uuid_request_body.dart';

part 'owner_staff_pay_api.g.dart';

@RestApi()
abstract class OwnerStaffPayApi {
  factory OwnerStaffPayApi(Dio dio, {String? baseUrl}) = _OwnerStaffPayApi;

  /// Staff pay rules of a branch.
  ///
  /// The branch's pay `period` (day, week, half_month, month: business days), its `sales_attribution` (at_payment:.
  /// a bill of a table handed to another waiter is the waiter's at payment time, the default; current_waiter: the.
  /// table's last waiter's), the choices the rule editor offers,.
  /// the staff who can work in the branch, and every rule version, by subject and start date. A rule is a staff.
  /// member's (`user_id`) or a role's (`role`); a person's own version wins over their role's; a version is in force.
  /// from `effective_from` until the subject's next one. staff_pay.view or staff_pay.manage.
  ///
  /// [branchId] - Filial (filialga biriktirilgan xodim uchun o'z filiali).
  @GET('/v1/owner/staff-pay/rules')
  Future<GetV1OwnerStaffPayRulesResponse> staffPayRuleIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add a wage rule (version).
  ///
  /// For one staff member (`user_id`) or a role (`role`: manager, cashier, waiter, cook, hostess, courier), in force.
  /// from `effective_from` (a business date). `components`: one or more parts that add up — each `{type, ...}`:.
  /// shift_rate {amount}; hourly_rate {amount}; sales_percent {percent}; service_charge_percent {percent};.
  /// guarantee {amount, per: shift|period, mode: max|plus, percent, base: sales|service_charge}; event_rate {amount,.
  /// event_types: booking kinds, empty for all but "other"}; tips {} (the person's tips, only when this part is.
  /// there). Amounts so'm (0–100 000 000), percents 0–100; a type once per rule; an empty list means no pay from that.
  /// date. 422 when the subject already has a version from that date. Audited. staff_pay.manage.
  @POST('/v1/owner/staff-pay/rules')
  Future<PostV1OwnerStaffPayRulesResponse> staffPayRuleStore({
    @Body() required V1OwnerStaffPayRulesRequestBody body,
  });

  /// Change a wage rule (version).
  ///
  /// Send what changes: `effective_from`, `components` (the whole list, as when adding), `note`. A closed statement.
  /// keeps what it used. Audited. staff_pay.manage.
  @PUT('/v1/owner/staff-pay/rules/{uuid}')
  Future<PutV1OwnerStaffPayRulesUuidResponse> staffPayRuleUpdate({
    @Path('uuid') required String uuid,
    @Body() V1OwnerStaffPayRulesUuidRequestBody? body,
  });

  /// Delete a wage rule (version).
  ///
  /// The subject's previous version (if any) is in force again from this one's date. Closed statements keep what.
  /// they used. Audited. staff_pay.manage.
  @DELETE('/v1/owner/staff-pay/rules/{uuid}')
  Future<DeleteV1OwnerStaffPayRulesUuidResponse> staffPayRuleDestroy({
    @Path('uuid') required String uuid,
  });

  /// Change the staff pay settings of a branch.
  ///
  /// Send what changes (at least one). `period`: day | week (Monday–Sunday) | half_month (1–15, 16–end) | month —.
  /// business days in the store's time zone; statements made from now on use it, existing ones keep theirs.
  /// `sales_attribution`: whose sales a bill of a table handed to another waiter is — at_payment (the waiter at.
  /// payment time, the default, the same as tips: bills paid before the handover stay with the first waiter) or.
  /// current_waiter (the table's last waiter, every bill of the order); draft statements take it at their next.
  /// computation, closed ones keep their numbers. A refund follows the person of the refunded bill. Audited.
  /// staff_pay.manage.
  @PUT('/v1/owner/staff-pay/settings')
  Future<PutV1OwnerStaffPaySettingsResponse> staffPayRuleSettings({
    @Body() V1OwnerStaffPaySettingsRequestBody? body,
  });

  /// Pay statements.
  ///
  /// Newest period first: the branches the caller sees (`branch_id` narrows to one), periods overlapping `from`–`to`.
  /// (business dates), `status` (draft, closed, paid), at most `limit` (50, up to 200). staff_pay.view or.
  /// staff_pay.manage.
  ///
  /// [from] - YYYY-MM-DD.
  ///
  /// [to] - YYYY-MM-DD.
  ///
  /// [status] - App\Domain\Pos\StaffPay\StatementStatus.
  @GET('/v1/owner/staff-pay/statements')
  Future<GetV1OwnerStaffPayStatementsResponse> staffPayStatementIndex({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('status') String? status,
    @Query('limit') int? limit,
  });

  /// Make a pay statement.
  ///
  /// A draft for the branch's pay period holding `date` (default: the branch's business day today): a line per staff.
  /// member with a rule and any work, sales or events in the period. 409 `statement_exists` (the period has one:.
  /// `statement_uuid`) or `statement_overlaps` (the pay period changed and another statement covers some of these.
  /// days). Audited. staff_pay.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/owner/staff-pay/statements')
  Future<PostV1OwnerStaffPayStatementsResponse> staffPayStatementStore({
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1OwnerStaffPayStatementsRequestBody? body,
  });

  /// A pay statement.
  ///
  /// Its lines: per person the components with what each gave and how (`detail`), the work and sales behind them.
  /// (`metrics`: shifts, minutes, bills, sales, service charge, events, tips, sessions not counted), the wage money.
  /// already given from the till in the period (`advances`), the adjustment and what is to pay; `warnings` (worked.
  /// without a rule, sessions left open); `open_shifts`: the branch's open till shifts a line can be paid from.
  /// staff_pay.view or staff_pay.manage.
  @GET('/v1/owner/staff-pay/statements/{uuid}')
  Future<GetV1OwnerStaffPayStatementsUuidResponse> staffPayStatementShow({
    @Path('uuid') required String uuid,
  });

  /// Delete a draft.
  ///
  /// 409 `statement_not_draft`. Audited. staff_pay.manage.
  @DELETE('/v1/owner/staff-pay/statements/{uuid}')
  Future<DeleteV1OwnerStaffPayStatementsUuidResponse> staffPayStatementDestroy({
    @Path('uuid') required String uuid,
  });

  /// Close a statement.
  ///
  /// Recomputed one last time and frozen; its lines can then be paid. 409 `statement_not_draft`. Audited.
  /// staff_pay.manage.
  @POST('/v1/owner/staff-pay/statements/{uuid}/close')
  Future<PostV1OwnerStaffPayStatementsUuidCloseResponse>
  staffPayStatementClose({@Path('uuid') required String uuid});

  /// Adjust a line of a draft.
  ///
  /// `adjustment` (so'm, + or −, e.g. "-50000" for a fine, "100000" for a bonus) with a `reason` (required unless 0);.
  /// it stays through recomputes. 409 `statement_not_draft`. Audited. staff_pay.manage.
  @PUT('/v1/owner/staff-pay/statements/{uuid}/lines/{line}')
  Future<PutV1OwnerStaffPayStatementsUuidLinesLineResponse>
  staffPayStatementAdjust({
    @Path('uuid') required String uuid,
    @Path('line') required String line,
    @Body() required V1OwnerStaffPayStatementsUuidLinesLineRequestBody body,
  });

  /// Pay a line.
  ///
  /// Of a closed statement, once. `method`: `cash_drawer` — from an open till shift of the branch (`shift_uuid`,.
  /// one of the statement's `open_shifts`): a `wage` cash movement (category payroll) of the line's amount for that.
  /// person, which the drawer must hold (409 `not_enough_cash`) and which shows in the shift's X/Z; or `other`.
  /// (bank transfer, card...) — only marked paid. An Idempotency-Key is required (428 without). 409 `statement_not_closed`,.
  /// `line_already_paid`; 422 `nothing_to_pay`, `shift_not_found`. Audited. staff_pay.manage (the till movement also.
  /// pos.cash_in_out).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/owner/staff-pay/statements/{uuid}/lines/{line}/pay')
  Future<PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse>
  staffPayStatementPay({
    @Path('uuid') required String uuid,
    @Path('line') required String line,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody body,
  });

  /// Recompute a draft.
  ///
  /// From the rules and the work and sales as they are now; adjustments stay. 409 `statement_not_draft`. Audited.
  /// staff_pay.manage.
  @POST('/v1/owner/staff-pay/statements/{uuid}/recompute')
  Future<PostV1OwnerStaffPayStatementsUuidRecomputeResponse>
  staffPayStatementRecompute({@Path('uuid') required String uuid});

  /// Reopen a closed statement.
  ///
  /// Back to a draft (`reason` required) while no line is paid: 409 `statement_not_closed`, `statement_has_payments`.
  /// Audited. staff_pay.manage.
  @POST('/v1/owner/staff-pay/statements/{uuid}/reopen')
  Future<PostV1OwnerStaffPayStatementsUuidReopenResponse>
  staffPayStatementReopen({
    @Path('uuid') required String uuid,
    @Body() required V1OwnerStaffPayStatementsUuidReopenRequestBody body,
  });

  /// Work sessions.
  ///
  /// Of a branch, by business date (`from`–`to`, YYYY-MM-DD, default: the last 7 days), optionally one person's.
  /// (`user_id`); newest first, at most 500. `open`: still clocked in; `abandoned`: left open on an earlier day and.
  /// set aside (not counted until it has an end). staff_pay.view or staff_pay.manage.
  ///
  /// [from] - YYYY-MM-DD.
  ///
  /// [to] - YYYY-MM-DD.
  @GET('/v1/owner/staff-pay/work-sessions')
  Future<GetV1OwnerStaffPayWorkSessionsResponse> staffWorkSessionIndex({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('user_id') int? userId,
  });

  /// Add a work session.
  ///
  /// For a staff member of the branch who did not clock in: `clock_in_at`, `clock_out_at` (ISO 8601 moments; without.
  /// an end it is open), `reason`. At most 24 hours, never overlapping another of the person's sessions, not in the.
  /// future (422). Audited. staff_pay.manage.
  @POST('/v1/owner/staff-pay/work-sessions')
  Future<PostV1OwnerStaffPayWorkSessionsResponse> staffWorkSessionStore({
    @Body() required V1OwnerStaffPayWorkSessionsRequestBody body,
  });

  /// Correct a work session.
  ///
  /// Its `clock_in_at` and `clock_out_at` (null: still open) with a `reason`; a set-aside session gets its end this.
  /// way. Same limits as adding. Audited. staff_pay.manage.
  @PUT('/v1/owner/staff-pay/work-sessions/{uuid}')
  Future<PutV1OwnerStaffPayWorkSessionsUuidResponse> staffWorkSessionUpdate({
    @Path('uuid') required String uuid,
    @Body() required V1OwnerStaffPayWorkSessionsUuidRequestBody body,
  });

  /// Remove a work session.
  ///
  /// With a `reason`. Audited. staff_pay.manage.
  @DELETE('/v1/owner/staff-pay/work-sessions/{uuid}')
  Future<DeleteV1OwnerStaffPayWorkSessionsUuidResponse>
  staffWorkSessionDestroy({
    @Path('uuid') required String uuid,
    @Query('reason') required String reason,
  });
}
