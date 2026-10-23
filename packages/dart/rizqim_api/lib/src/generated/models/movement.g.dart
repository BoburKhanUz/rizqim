// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'movement.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Movement _$MovementFromJson(Map<String, dynamic> json) => Movement(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  category: json['category'] as String?,
  amount: json['amount'] as String,
  note: json['note'] as String?,
  employeeId: (json['employee_id'] as num?)?.toInt(),
  employee: json['employee'] == null
      ? null
      : Employee4.fromJson(json['employee'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User22.fromJson(json['user'] as Map<String, dynamic>),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  approver: json['approver'] == null
      ? null
      : Approver4.fromJson(json['approver'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$MovementToJson(Movement instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'category': instance.category,
  'amount': instance.amount,
  'note': instance.note,
  'employee_id': instance.employeeId,
  'employee': instance.employee,
  'user': instance.user,
  'approved_by': instance.approvedBy,
  'approver': instance.approver,
  'created_at': instance.createdAt,
};
