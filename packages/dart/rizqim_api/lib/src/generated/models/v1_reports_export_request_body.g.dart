// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_reports_export_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1ReportsExportRequestBody _$V1ReportsExportRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1ReportsExportRequestBody(
  timeframe: Timeframe.fromJson(json['timeframe'] as String),
  startDate: json['start_date'] == null
      ? null
      : DateTime.parse(json['start_date'] as String),
  endDate: json['end_date'] == null
      ? null
      : DateTime.parse(json['end_date'] as String),
);

Map<String, dynamic> _$V1ReportsExportRequestBodyToJson(
  V1ReportsExportRequestBody instance,
) => <String, dynamic>{
  'timeframe': instance.timeframe,
  'start_date': instance.startDate?.toIso8601String(),
  'end_date': instance.endDate?.toIso8601String(),
};
