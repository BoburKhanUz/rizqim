// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_audit_logs_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerAuditLogsResponse _$GetV1OwnerAuditLogsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerAuditLogsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data47.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerAuditLogsResponseToJson(
  GetV1OwnerAuditLogsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
