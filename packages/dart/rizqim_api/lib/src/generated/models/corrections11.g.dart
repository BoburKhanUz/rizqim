// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'corrections11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Corrections11 _$Corrections11FromJson(Map<String, dynamic> json) =>
    Corrections11(
      amount: json['amount'] as String,
      mode: json['mode'] as String,
      staffId: (json['staff_id'] as num?)?.toInt(),
      reason: json['reason'] as String?,
      by: json['by'] == null
          ? null
          : By10.fromJson(json['by'] as Map<String, dynamic>),
      approvedBy: (json['approved_by'] as num?)?.toInt(),
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$Corrections11ToJson(Corrections11 instance) =>
    <String, dynamic>{
      'amount': instance.amount,
      'mode': instance.mode,
      'staff_id': instance.staffId,
      'reason': instance.reason,
      'by': instance.by,
      'approved_by': instance.approvedBy,
      'created_at': instance.createdAt,
    };
