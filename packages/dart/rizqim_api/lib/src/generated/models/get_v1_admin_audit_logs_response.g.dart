// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_audit_logs_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminAuditLogsResponse _$GetV1AdminAuditLogsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminAuditLogsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data2.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1AdminAuditLogsResponseToJson(
  GetV1AdminAuditLogsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
