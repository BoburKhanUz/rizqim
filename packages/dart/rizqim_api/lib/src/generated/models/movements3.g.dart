// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'movements3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Movements3 _$Movements3FromJson(Map<String, dynamic> json) => Movements3(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  category: json['category'] as String?,
  amount: json['amount'] as String,
  note: json['note'] as String?,
  employee: json['employee'] == null
      ? null
      : Employee3.fromJson(json['employee'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User21.fromJson(json['user'] as Map<String, dynamic>),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
  approver: json['approver'] == null
      ? null
      : Approver3.fromJson(json['approver'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Movements3ToJson(Movements3 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'type': instance.type,
      'category': instance.category,
      'amount': instance.amount,
      'note': instance.note,
      'employee': instance.employee,
      'user': instance.user,
      'approved_by': instance.approvedBy,
      'approver': instance.approver,
      'created_at': instance.createdAt,
    };
