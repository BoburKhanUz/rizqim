// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_shifts_uuid_x_report_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosShiftsUuidXReportResponse _$GetV1PosShiftsUuidXReportResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosShiftsUuidXReportResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data169.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosShiftsUuidXReportResponseToJson(
  GetV1PosShiftsUuidXReportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
