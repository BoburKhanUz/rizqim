// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cash6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cash6 _$Cash6FromJson(Map<String, dynamic> json) => Cash6(
  opening: json['opening'] as String,
  sales: json['sales'] as String,
  refunds: json['refunds'] as String,
  cashIn: json['cash_in'] as String,
  cashOut: json['cash_out'] as String,
  wages: json['wages'] as String,
  purchases: json['purchases'] as String,
  deposits: json['deposits'] as String,
  depositRefunds: json['deposit_refunds'] as String,
  repayments: json['repayments'] as String,
  tips: json['tips'] as String,
  tipPayouts: json['tip_payouts'] as String,
  expected: json['expected'] as String,
);

Map<String, dynamic> _$Cash6ToJson(Cash6 instance) => <String, dynamic>{
  'opening': instance.opening,
  'sales': instance.sales,
  'refunds': instance.refunds,
  'cash_in': instance.cashIn,
  'cash_out': instance.cashOut,
  'wages': instance.wages,
  'purchases': instance.purchases,
  'deposits': instance.deposits,
  'deposit_refunds': instance.depositRefunds,
  'repayments': instance.repayments,
  'tips': instance.tips,
  'tip_payouts': instance.tipPayouts,
  'expected': instance.expected,
};
