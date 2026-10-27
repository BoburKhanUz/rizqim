// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_team_id_invite_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminTeamIdInviteResponse _$PostV1AdminTeamIdInviteResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminTeamIdInviteResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data20.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminTeamIdInviteResponseToJson(
  PostV1AdminTeamIdInviteResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
