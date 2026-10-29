// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_fiscal_bank_reports_response.dart';
import '../models/get_v1_owner_fiscal_readiness_response.dart';
import '../models/get_v1_owner_fiscal_receipts_response.dart';
import '../models/get_v1_owner_fiscal_receipts_uuid_response.dart';
import '../models/get_v1_owner_fiscal_settings_response.dart';
import '../models/get_v1_owner_fiscal_terminal_flow_response.dart';
import '../models/payment_form.dart';
import '../models/post_v1_owner_fiscal_receipts_uuid_retry_response.dart';
import '../models/put_v1_owner_branches_branch_terminal_flow_response.dart';
import '../models/put_v1_owner_fiscal_bank_reports_uuid_response.dart';
import '../models/put_v1_owner_fiscal_devices_uuid_response.dart';
import '../models/put_v1_owner_fiscal_settings_response.dart';
import '../models/status5.dart';
import '../models/type6.dart';
import '../models/v1_owner_branches_branch_terminal_flow_request_body.dart';
import '../models/v1_owner_fiscal_bank_reports_uuid_request_body.dart';
import '../models/v1_owner_fiscal_devices_uuid_request_body.dart';
import '../models/v1_owner_fiscal_settings_request_body.dart';

part 'owner_fiscal_api.g.dart';

@RestApi()
abstract class OwnerFiscalApi {
  factory OwnerFiscalApi(Dio dio, {String? baseUrl}) = _OwnerFiscalApi;

  /// Change a branch's card flow.
  ///
  /// Send only what changes: `card_mode` terminal_only | smart_kassa | none (null: not chosen), `checklist`.
  /// {old_kassa_unused, bank_terminal_mode, scanner_2d: bool} (ticking records who and when, unticking clears it),.
  /// `difference_threshold` (so'm, 0–100000000: a bank terminal total further than this from the Z's card total.
  /// alerts the owner; 0: any difference), `ask_bank_total` (the till asks for the bank terminal's day total at the.
  /// Z), `shared_terminal` (the branch's tills share one bank terminal, or a till runs several shifts a day, settled.
  /// once per business day: one bank report covers every shift of that day — enter the day's total at the last Z).
  /// Audited. settings.manage; 403 `module_disabled` without the terminal_card_flow module.
  @PUT('/v1/owner/branches/{branch}/terminal-flow')
  Future<PutV1OwnerBranchesBranchTerminalFlowResponse> terminalFlowUpdate({
    @Path('branch') required int branch,
    @Body() V1OwnerBranchesBranchTerminalFlowRequestBody? body,
  });

  /// Bank terminal reports.
  ///
  /// The bank terminal day totals entered at the Z (or after it), newest business day first: the branches the caller.
  /// sees (`branch_id` narrows to one), a business day range (`from`, `to`, YYYY-MM-DD), `over_threshold=1` for the.
  /// mismatches only (an alerted report stays one after a correction); at most `limit` (50, up to 200). A shared.
  /// terminal's report covers several shifts (`shared`, `shifts`; `final` once all are closed). finance.view or.
  /// reports.view.
  ///
  /// [branchId] - Filial.
  ///
  /// [from] - Business day from (YYYY-MM-DD).
  ///
  /// [to] - Business day to (YYYY-MM-DD).
  ///
  /// [overThreshold] - Only the mismatches.
  @GET('/v1/owner/fiscal/bank-reports')
  Future<GetV1OwnerFiscalBankReportsResponse> terminalFlowReports({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('over_threshold') bool? overThreshold,
    @Query('limit') int? limit,
  });

  /// Enter or correct a shift's bank terminal report.
  ///
  /// As the till's PUT /pos/shifts/{uuid}/bank-report (same body), from the panel: for a shift whose Z went without.
  /// it, or to correct it. Audited (the replaced entry stays in `history`); a final difference above the threshold.
  /// alerts the owner, and a correction never clears an alert. An open shift: finance.manage or pos.close_shift (the.
  /// latter sees the comparison only once final); a closed one: finance.manage only (403 `bank_report_locked`).
  /// 403 `module_disabled` without the terminal_card_flow module on the shift's branch.
  @PUT('/v1/owner/fiscal/bank-reports/{uuid}')
  Future<PutV1OwnerFiscalBankReportsUuidResponse> terminalFlowEnter({
    @Path('uuid') required String uuid,
    @Body() V1OwnerFiscalBankReportsUuidRequestBody? body,
  });

  /// Mark a till as a smart kassa.
  ///
  /// `smart_kassa`: this POS terminal is an Android smart kassa (Sunmi P2/P3...): in fiscal mode its receipts go to.
  /// the store's `smart_kassa_driver` (the provider's virtual kassa bound to it); `fiscal_kassa_id`: the id the.
  /// provider knows that kassa by (null clears it): 6–32 latin letters, digits and dashes (stored upper case), used.
  /// by no other active device of the store and no active smart kassa anywhere, and — on a smart kassa — a kassa of.
  /// the store's STIR at the provider (422 `fiscal_kassa_id` otherwise; the store needs its STIR first).
  /// `kassa_status`: what the provider says about it (null when the driver cannot say). Audited. settings.manage; a terminal of another branch the caller does not manage: 404;.
  /// 403 `module_disabled` without the terminal_card_flow module on the till's branch.
  @PUT('/v1/owner/fiscal/devices/{uuid}')
  Future<PutV1OwnerFiscalDevicesUuidResponse> terminalFlowUpdateDevice({
    @Path('uuid') required String uuid,
    @Body() V1OwnerFiscalDevicesUuidRequestBody? body,
  });

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
  /// receipts go now (`null` while fiscal mode is off: receipts are recorded as "fiskal emas"); P6-16:.
  /// `smart_kassa_driver` is where a smart kassa's receipts go in fiscal mode.
  @GET('/v1/owner/fiscal/settings')
  Future<GetV1OwnerFiscalSettingsResponse> fiscalSettingsShow();

  /// Update fiscal settings.
  ///
  /// Each field is optional (left out = kept). `tin` 9 digits; `tax_regime` vat (12%) | vat_simplified (6%) |.
  /// turnover (no VAT). `fiscal_settings.charges.<charge>` sets the receipt line of a charge (room_fee, place_fee,.
  /// min_spend_topup, service_charge, deposit, booking_fee, credit, delivery): `mxik` 17 digits, `package_code`.
  /// digits; a null field goes back to the default. `deposit_refund_receipt` / `retained_deposit_receipt`: whether a.
  /// booking deposit given back / kept gets a receipt (null: the default). P6-16: `smart_kassa_driver` — the driver.
  /// of POS terminals flagged smart kassa (the provider's virtual kassa bound to the device; null: `driver`). Turning.
  /// `enabled` on needs the TIN and the tax regime.
  @PUT('/v1/owner/fiscal/settings')
  Future<PutV1OwnerFiscalSettingsResponse> fiscalSettingsUpdate({
    @Body() V1OwnerFiscalSettingsRequestBody? body,
  });

  /// Card flow of the branches.
  ///
  /// Every branch the caller sees (`branch_id` narrows to one): whether its format has the terminal_card_flow module.
  /// (`module_enabled`), its setup — `card_mode` (terminal_only: a bank terminal its TXKM switched to "faqat.
  ///      * terminal"; smart_kassa: Rizqim on an Android smart kassa, receipts through the provider's virtual kassa; none;.
  /// null: not chosen), the installation `checklist` (old kassa not used for sales, bank terminal mode set, 2D.
  /// scanner present) with who ticked each and when, `difference_threshold`, `ask_bank_total` — and its POS tills.
  /// with their smart kassa flag. `fiscal`: the store's fiscal mode and where a smart kassa's receipts go.
  /// settings.manage.
  ///
  /// [branchId] - Filial.
  @GET('/v1/owner/fiscal/terminal-flow')
  Future<GetV1OwnerFiscalTerminalFlowResponse> terminalFlowIndex({
    @Query('branch_id') int? branchId,
  });
}
