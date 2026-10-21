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
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$MovementToJson(Movement instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'category': instance.category,
  'amount': instance.amount,
  'note': instance.note,
  'employee_id': instance.employeeId,
  'approved_by': instance.approvedBy,
  'created_at': instance.createdAt,
};
