// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_reports_export_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1ReportsExportResponse _$PostV1ReportsExportResponseFromJson(
  Map<String, dynamic> json,
) => PostV1ReportsExportResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$PostV1ReportsExportResponseToJson(
  PostV1ReportsExportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
