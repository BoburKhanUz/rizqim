// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_finance_ledger_response.dart';
import '../models/get_v1_owner_finance_wallet_response.dart';
import '../models/get_v1_owner_payments_response.dart';
import '../models/get_v1_owner_payouts_response.dart';
import '../models/status4.dart';

part 'owner_finance_history_api.g.dart';

@RestApi()
abstract class OwnerFinanceHistoryApi {
  factory OwnerFinanceHistoryApi(Dio dio, {String? baseUrl}) =
      _OwnerFinanceHistoryApi;

  /// Debt ledger.
  ///
  /// The store's debt balance movements (store_transactions): commission (debt up), top-up (debt down),.
  /// subscription invoices and their voids, refunds and adjustments. A positive `amount` raises the debt.
  ///
  /// [type] - commission | topup | refund | adjustment | subscription | settlement | payout.
  ///
  /// [from] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [to] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [perPage] - 1–100, standart 25.
  ///
  /// [page] - Sahifa raqami, 1 dan (Laravel sahifalari).
  @GET('/v1/owner/finance/ledger')
  Future<GetV1OwnerFinanceLedgerResponse> financeHistoryLedger({
    @Query('type') String? type,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });

  /// Wallet.
  ///
  /// The store's wallet movements (store_wallet_transactions): customers' online payments settled to the store.
  /// (wallet up) and payouts (wallet down).
  ///
  /// [type] - settlement | payout | ...
  ///
  /// [from] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [to] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [perPage] - 1–100, standart 25.
  ///
  /// [page] - Sahifa raqami, 1 dan (Laravel sahifalari).
  @GET('/v1/owner/finance/wallet')
  Future<GetV1OwnerFinanceWalletResponse> financeHistoryWallet({
    @Query('type') String? type,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });

  /// Online payments.
  ///
  /// Customers' payments for the store's orders through Click, Payme… (the payments table), with the provider's.
  /// transaction id for reconciliation. A branch-bound caller sees their branch's orders only. Raw provider payloads.
  /// are never returned. `fiscal` (P3-14): the fiscal receipt Payme/Click registers for the payment — `status`.
  /// blocked | pending | failed | recorded and problem codes (`mxik_missing`, `store_tin_missing`...); null when.
  /// fiscal receipts are off. `fiscal_status=blocked` lists the payments that need the owner's attention.
  ///
  /// [status] - pending | paid | failed | cancelled | expired | refunded.
  ///
  /// [provider] - click | payme | ...
  ///
  /// [fiscalStatus] - P3-14: blocked | pending | failed | recorded (fiskal chek holati).
  ///
  /// [branchId] - Filial.
  ///
  /// [from] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [to] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [perPage] - 1–100, standart 25.
  ///
  /// [page] - Sahifa raqami, 1 dan (Laravel sahifalari).
  @GET('/v1/owner/payments')
  Future<GetV1OwnerPaymentsResponse> financeHistoryPayments({
    @Query('status') String? status,
    @Query('provider') String? provider,
    @Query('fiscal_status') String? fiscalStatus,
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });

  /// Payout requests.
  ///
  /// The store's requests to withdraw from the wallet and how the platform handled them. The card number is masked.
  ///
  /// [status] - pending | completed | rejected.
  ///
  /// [from] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [to] - YYYY-MM-DD (do'kon vaqti).
  ///
  /// [perPage] - 1–100, standart 25.
  ///
  /// [page] - Sahifa raqami, 1 dan (Laravel sahifalari).
  @GET('/v1/owner/payouts')
  Future<GetV1OwnerPayoutsResponse> financeHistoryPayouts({
    @Query('status') Status4? status,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });
}
