// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cash_movement.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CashMovement _$CashMovementFromJson(Map<String, dynamic> json) => CashMovement(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  shiftId: (json['shift_id'] as num).toInt(),
  type: CashMovementType.fromJson(json['type'] as String),
  category: json['category'] as String?,
  amount: json['amount'] as String,
  note: json['note'] as String?,
  paymentId: (json['payment_id'] as num?)?.toInt(),
  userId: (json['user_id'] as num?)?.toInt(),
  employeeId: (json['employee_id'] as num?)?.toInt(),
  deviceId: (json['device_id'] as num?)?.toInt(),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  shift: json['shift'] == null
      ? null
      : PosShift.fromJson(json['shift'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  employee: json['employee'] == null
      ? null
      : User.fromJson(json['employee'] as Map<String, dynamic>),
);

Map<String, dynamic> _$CashMovementToJson(CashMovement instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'store_id': instance.storeId,
      'branch_id': instance.branchId,
      'shift_id': instance.shiftId,
      'type': instance.type,
      'category': instance.category,
      'amount': instance.amount,
      'note': instance.note,
      'payment_id': instance.paymentId,
      'user_id': instance.userId,
      'employee_id': instance.employeeId,
      'device_id': instance.deviceId,
      'approved_by': instance.approvedBy,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'shift': instance.shift,
      'user': instance.user,
      'employee': instance.employee,
    };
