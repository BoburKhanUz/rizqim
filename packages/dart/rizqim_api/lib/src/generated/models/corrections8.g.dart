// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'corrections8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Corrections8 _$Corrections8FromJson(Map<String, dynamic> json) => Corrections8(
  amount: json['amount'] as String,
  mode: json['mode'] as String,
  staffId: (json['staff_id'] as num?)?.toInt(),
  reason: json['reason'] as String?,
  by: json['by'] == null
      ? null
      : By7.fromJson(json['by'] as Map<String, dynamic>),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Corrections8ToJson(Corrections8 instance) =>
    <String, dynamic>{
      'amount': instance.amount,
      'mode': instance.mode,
      'staff_id': instance.staffId,
      'reason': instance.reason,
      'by': instance.by,
      'approved_by': instance.approvedBy,
      'created_at': instance.createdAt,
    };
