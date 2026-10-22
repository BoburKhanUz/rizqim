// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'cash_movement_type.dart';
import 'pos_shift.dart';
import 'user.dart';

part 'cash_movement.g.dart';

@JsonSerializable()
class CashMovement {
  const CashMovement({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.branchId,
    required this.shiftId,
    required this.type,
    required this.category,
    required this.amount,
    required this.note,
    required this.paymentId,
    required this.userId,
    required this.employeeId,
    required this.deviceId,
    required this.approvedBy,
    required this.createdAt,
    required this.updatedAt,
    this.shift,
    this.user,
    this.employee,
  });

  factory CashMovement.fromJson(Map<String, Object?> json) =>
      _$CashMovementFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'shift_id')
  final int shiftId;
  final CashMovementType type;
  final String? category;
  final String amount;
  final String? note;
  @JsonKey(name: 'payment_id')
  final int? paymentId;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'employee_id')
  final int? employeeId;
  @JsonKey(name: 'device_id')
  final int? deviceId;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final PosShift? shift;
  final User? user;
  final User? employee;

  Map<String, Object?> toJson() => _$CashMovementToJson(this);
}
