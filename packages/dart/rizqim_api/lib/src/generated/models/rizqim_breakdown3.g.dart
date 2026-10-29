// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rizqim_breakdown3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RizqimBreakdown3 _$RizqimBreakdown3FromJson(Map<String, dynamic> json) =>
    RizqimBreakdown3(
      payments: json['payments'] as String,
      deposits: json['deposits'] as String,
      repayments: json['repayments'] as String,
      refunds: json['refunds'] as String,
      depositRefunds: json['deposit_refunds'] as String,
      tips: json['tips'] as String,
    );

Map<String, dynamic> _$RizqimBreakdown3ToJson(RizqimBreakdown3 instance) =>
    <String, dynamic>{
      'payments': instance.payments,
      'deposits': instance.deposits,
      'repayments': instance.repayments,
      'refunds': instance.refunds,
      'deposit_refunds': instance.depositRefunds,
      'tips': instance.tips,
    };
