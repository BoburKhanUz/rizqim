// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rizqim_breakdown2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RizqimBreakdown2 _$RizqimBreakdown2FromJson(Map<String, dynamic> json) =>
    RizqimBreakdown2(
      payments: json['payments'] as String,
      deposits: json['deposits'] as String,
      repayments: json['repayments'] as String,
      refunds: json['refunds'] as String,
      depositRefunds: json['deposit_refunds'] as String,
    );

Map<String, dynamic> _$RizqimBreakdown2ToJson(RizqimBreakdown2 instance) =>
    <String, dynamic>{
      'payments': instance.payments,
      'deposits': instance.deposits,
      'repayments': instance.repayments,
      'refunds': instance.refunds,
      'deposit_refunds': instance.depositRefunds,
    };
