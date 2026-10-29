// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data67.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data67 _$Data67FromJson(Map<String, dynamic> json) => Data67(
  debtBalance: json['debt_balance'] as num,
  walletBalance: json['wallet_balance'] as num,
  creditLimit: json['credit_limit'] as num,
  minPayoutReserve: json['min_payout_reserve'] as num,
  paymentAccountType: json['payment_account_type'] as String,
  commissionRate: json['commission_rate'] as num,
  status: StoreStatus.fromJson(json['status'] as String),
  isOrderLocked: json['is_order_locked'] as bool,
  ledgerTransactions: (json['ledger_transactions'] as List<dynamic>)
      .map((e) => StoreTransaction.fromJson(e as Map<String, dynamic>))
      .toList(),
  walletTransactions: (json['wallet_transactions'] as List<dynamic>)
      .map((e) => StoreWalletTransaction.fromJson(e as Map<String, dynamic>))
      .toList(),
  payoutRequests: (json['payout_requests'] as List<dynamic>)
      .map((e) => PayoutRequests.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data67ToJson(Data67 instance) => <String, dynamic>{
  'debt_balance': instance.debtBalance,
  'wallet_balance': instance.walletBalance,
  'credit_limit': instance.creditLimit,
  'min_payout_reserve': instance.minPayoutReserve,
  'payment_account_type': instance.paymentAccountType,
  'commission_rate': instance.commissionRate,
  'status': instance.status,
  'is_order_locked': instance.isOrderLocked,
  'ledger_transactions': instance.ledgerTransactions,
  'wallet_transactions': instance.walletTransactions,
  'payout_requests': instance.payoutRequests,
};
