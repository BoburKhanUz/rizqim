// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cancellation4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cancellation4 _$Cancellation4FromJson(Map<String, dynamic> json) =>
    Cancellation4(
      daysBefore: (json['days_before'] as num).toInt(),
      rulePercent: json['rule_percent'] as String,
      paid: json['paid'] as String,
      computedRetained: json['computed_retained'] as String,
      retained: json['retained'] as String,
      refund: json['refund'] as String,
      overrideReason: json['override_reason'] as String?,
      reason: json['reason'] as String?,
      businessDate: json['business_date'] as String?,
      cancelledBy: (json['cancelled_by'] as num?)?.toInt(),
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$Cancellation4ToJson(Cancellation4 instance) =>
    <String, dynamic>{
      'days_before': instance.daysBefore,
      'rule_percent': instance.rulePercent,
      'paid': instance.paid,
      'computed_retained': instance.computedRetained,
      'retained': instance.retained,
      'refund': instance.refund,
      'override_reason': instance.overrideReason,
      'reason': instance.reason,
      'business_date': instance.businessDate,
      'cancelled_by': instance.cancelledBy,
      'created_at': instance.createdAt,
    };
