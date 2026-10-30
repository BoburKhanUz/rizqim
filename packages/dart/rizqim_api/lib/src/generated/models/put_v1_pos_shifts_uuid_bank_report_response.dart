// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data260.dart';

part 'put_v1_pos_shifts_uuid_bank_report_response.g.dart';

@JsonSerializable()
class PutV1PosShiftsUuidBankReportResponse {
  const PutV1PosShiftsUuidBankReportResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1PosShiftsUuidBankReportResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1PosShiftsUuidBankReportResponseFromJson(json);

  final bool success;
  final String message;
  final Data260 data;

  Map<String, Object?> toJson() =>
      _$PutV1PosShiftsUuidBankReportResponseToJson(this);
}
