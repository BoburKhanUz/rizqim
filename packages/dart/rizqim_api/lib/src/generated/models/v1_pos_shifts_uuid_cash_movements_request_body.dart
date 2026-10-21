// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type8.dart';

part 'v1_pos_shifts_uuid_cash_movements_request_body.g.dart';

@JsonSerializable()
class V1PosShiftsUuidCashMovementsRequestBody {
  const V1PosShiftsUuidCashMovementsRequestBody({
    required this.type,
    required this.category,
    required this.amount,
    this.note,
    this.employeeId,
    this.approvalToken,
  });

  factory V1PosShiftsUuidCashMovementsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosShiftsUuidCashMovementsRequestBodyFromJson(json);

  final Type8 type;
  final String category;
  final num amount;
  final String? note;
  @JsonKey(name: 'employee_id')
  final int? employeeId;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosShiftsUuidCashMovementsRequestBodyToJson(this);
}
