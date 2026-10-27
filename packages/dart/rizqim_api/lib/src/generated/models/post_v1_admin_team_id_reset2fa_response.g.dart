// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_team_id_reset2fa_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminTeamIdReset2faResponse _$PostV1AdminTeamIdReset2faResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminTeamIdReset2faResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data21.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminTeamIdReset2faResponseToJson(
  PostV1AdminTeamIdReset2faResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
