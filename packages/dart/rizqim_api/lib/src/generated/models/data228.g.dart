// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data228.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data228 _$Data228FromJson(Map<String, dynamic> json) => Data228(
  daysBefore: (json['days_before'] as num).toInt(),
  rulePercent: json['rule_percent'] as String,
  paid: json['paid'] as String,
  computedRetained: json['computed_retained'] as String,
  retained: json['retained'] as String,
  refund: json['refund'] as String,
  overridden: json['overridden'] as bool,
  cashRefund: json['cash_refund'] as String,
  transferRefund: json['transfer_refund'] as String,
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data228ToJson(Data228 instance) => <String, dynamic>{
  'days_before': instance.daysBefore,
  'rule_percent': instance.rulePercent,
  'paid': instance.paid,
  'computed_retained': instance.computedRetained,
  'retained': instance.retained,
  'refund': instance.refund,
  'overridden': instance.overridden,
  'cash_refund': instance.cashRefund,
  'transfer_refund': instance.transferRefund,
  'rows': instance.rows,
};
