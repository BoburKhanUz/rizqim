// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rizqim_breakdown4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RizqimBreakdown4 _$RizqimBreakdown4FromJson(Map<String, dynamic> json) =>
    RizqimBreakdown4(
      payments: json['payments'] as String,
      deposits: json['deposits'] as String,
      repayments: json['repayments'] as String,
      refunds: json['refunds'] as String,
      depositRefunds: json['deposit_refunds'] as String,
    );

Map<String, dynamic> _$RizqimBreakdown4ToJson(RizqimBreakdown4 instance) =>
    <String, dynamic>{
      'payments': instance.payments,
      'deposits': instance.deposits,
      'repayments': instance.repayments,
      'refunds': instance.refunds,
      'deposit_refunds': instance.depositRefunds,
    };
