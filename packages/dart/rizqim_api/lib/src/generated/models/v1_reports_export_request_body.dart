// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'timeframe.dart';

part 'v1_reports_export_request_body.g.dart';

@JsonSerializable()
class V1ReportsExportRequestBody {
  const V1ReportsExportRequestBody({
    required this.timeframe,
    this.startDate,
    this.endDate,
  });

  factory V1ReportsExportRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1ReportsExportRequestBodyFromJson(json);

  final Timeframe timeframe;
  @JsonKey(name: 'start_date')
  final DateTime? startDate;
  @JsonKey(name: 'end_date')
  final DateTime? endDate;

  Map<String, Object?> toJson() => _$V1ReportsExportRequestBodyToJson(this);
}
