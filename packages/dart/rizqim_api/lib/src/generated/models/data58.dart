// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store_status.dart';
import 'store_transaction.dart';
import 'store_wallet_transaction.dart';
import 'payout_requests.dart';

part 'data58.g.dart';

@JsonSerializable()
class Data58 {
  const Data58({
    required this.debtBalance,
    required this.walletBalance,
    required this.creditLimit,
    required this.minPayoutReserve,
    required this.paymentAccountType,
    required this.commissionRate,
    required this.status,
    required this.isOrderLocked,
    required this.ledgerTransactions,
    required this.walletTransactions,
    required this.payoutRequests,
  });

  factory Data58.fromJson(Map<String, Object?> json) => _$Data58FromJson(json);

  @JsonKey(name: 'debt_balance')
  final num debtBalance;
  @JsonKey(name: 'wallet_balance')
  final num walletBalance;
  @JsonKey(name: 'credit_limit')
  final num creditLimit;
  @JsonKey(name: 'min_payout_reserve')
  final num minPayoutReserve;
  @JsonKey(name: 'payment_account_type')
  final String paymentAccountType;
  @JsonKey(name: 'commission_rate')
  final num commissionRate;
  final StoreStatus status;
  @JsonKey(name: 'is_order_locked')
  final bool isOrderLocked;
  @JsonKey(name: 'ledger_transactions')
  final List<StoreTransaction> ledgerTransactions;
  @JsonKey(name: 'wallet_transactions')
  final List<StoreWalletTransaction> walletTransactions;
  @JsonKey(name: 'payout_requests')
  final List<PayoutRequests> payoutRequests;

  Map<String, Object?> toJson() => _$Data58ToJson(this);
}
