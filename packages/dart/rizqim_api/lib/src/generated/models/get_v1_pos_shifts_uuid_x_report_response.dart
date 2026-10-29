// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data206.dart';

part 'get_v1_pos_shifts_uuid_x_report_response.g.dart';

@JsonSerializable()
class GetV1PosShiftsUuidXReportResponse {
  const GetV1PosShiftsUuidXReportResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosShiftsUuidXReportResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosShiftsUuidXReportResponseFromJson(json);

  final bool success;
  final String message;
  final Data206 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosShiftsUuidXReportResponseToJson(this);
}
