// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_admin_auth_sessions_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1AdminAuthSessionsIdResponse
_$DeleteV1AdminAuthSessionsIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1AdminAuthSessionsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'] as Map<String, dynamic>,
    );

Map<String, dynamic> _$DeleteV1AdminAuthSessionsIdResponseToJson(
  DeleteV1AdminAuthSessionsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
