// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_shifts_uuid_cash_movements_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosShiftsUuidCashMovementsRequestBody
_$V1PosShiftsUuidCashMovementsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosShiftsUuidCashMovementsRequestBody(
      type: Type8.fromJson(json['type'] as String),
      category: json['category'] as String,
      amount: json['amount'] as num,
      note: json['note'] as String?,
      employeeId: (json['employee_id'] as num?)?.toInt(),
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosShiftsUuidCashMovementsRequestBodyToJson(
  V1PosShiftsUuidCashMovementsRequestBody instance,
) => <String, dynamic>{
  'type': instance.type,
  'category': instance.category,
  'amount': instance.amount,
  'note': instance.note,
  'employee_id': instance.employeeId,
  'approval_token': instance.approvalToken,
};
