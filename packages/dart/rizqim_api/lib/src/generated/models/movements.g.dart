// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'movements.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Movements _$MovementsFromJson(Map<String, dynamic> json) => Movements(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  category: json['category'] as String?,
  amount: json['amount'] as String,
  note: json['note'] as String?,
  employee: json['employee'] == null
      ? null
      : Employee.fromJson(json['employee'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User19.fromJson(json['user'] as Map<String, dynamic>),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
  approver: json['approver'] == null
      ? null
      : Approver.fromJson(json['approver'] as Map<String, dynamic>),
);

Map<String, dynamic> _$MovementsToJson(Movements instance) => <String, dynamic>{
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
