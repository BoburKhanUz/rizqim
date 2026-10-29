// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rizqim_breakdown.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RizqimBreakdown _$RizqimBreakdownFromJson(Map<String, dynamic> json) =>
    RizqimBreakdown(
      payments: json['payments'] as String,
      deposits: json['deposits'] as String,
      repayments: json['repayments'] as String,
      refunds: json['refunds'] as String,
      depositRefunds: json['deposit_refunds'] as String,
    );

Map<String, dynamic> _$RizqimBreakdownToJson(RizqimBreakdown instance) =>
    <String, dynamic>{
      'payments': instance.payments,
      'deposits': instance.deposits,
      'repayments': instance.repayments,
      'refunds': instance.refunds,
      'deposit_refunds': instance.depositRefunds,
    };
