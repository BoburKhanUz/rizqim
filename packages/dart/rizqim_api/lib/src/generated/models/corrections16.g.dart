// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'corrections16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Corrections16 _$Corrections16FromJson(Map<String, dynamic> json) =>
    Corrections16(
      amount: json['amount'] as String,
      mode: json['mode'] as String,
      staffId: (json['staff_id'] as num?)?.toInt(),
      reason: json['reason'] as String?,
      by: json['by'] == null
          ? null
          : By9.fromJson(json['by'] as Map<String, dynamic>),
      approvedBy: (json['approved_by'] as num?)?.toInt(),
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$Corrections16ToJson(Corrections16 instance) =>
    <String, dynamic>{
      'amount': instance.amount,
      'mode': instance.mode,
      'staff_id': instance.staffId,
      'reason': instance.reason,
      'by': instance.by,
      'approved_by': instance.approvedBy,
      'created_at': instance.createdAt,
    };
