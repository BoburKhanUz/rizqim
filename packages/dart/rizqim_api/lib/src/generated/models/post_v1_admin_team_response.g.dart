// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_team_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminTeamResponse _$PostV1AdminTeamResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AdminTeamResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data17.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AdminTeamResponseToJson(
  PostV1AdminTeamResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
